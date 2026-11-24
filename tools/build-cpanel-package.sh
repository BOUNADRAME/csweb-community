#!/usr/bin/env bash
# Build a ready-to-upload CSWeb package for shared hosting (cPanel / Plesk / DirectAdmin).
#
# The result is a zip that the hosting customer extracts with the hosting file
# manager: no SSH, Composer or Node is needed on the server.
#
#   tools/build-cpanel-package.sh [git-ref]        (default: HEAD)
#
# Environment overrides:
#   OUT_DIR            output folder                      (default: ./build/cpanel)
#   CSWEB_RELEASE_URL  upstream CSWeb archive, used for the pre-built
#                      bower_components (jQuery, Bootstrap, DataTables, FontAwesome)
#   BOWER_REF          fallback: a tag of this repository that still contains
#                      bower_components/ from the original upstream import
#                      (fetched from origin when missing locally)
#   COMPOSER_BIN       composer command                   (default: composer, or ./composer.phar)
#
# What the package contains / leaves out:
#   + application code at <git-ref>, vendor/ (production dependencies only),
#     bower_components/, empty writable folders (var/, files/, files_csweb/),
#     INSTALL-cPanel.pdf/.md, BUILD-INFO.txt
#   - Docker files, tests, CI, developer docs, PHPUnit, .env files, local config
set -euo pipefail

REF="${1:-HEAD}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${OUT_DIR:-$ROOT/build/cpanel}"
CSWEB_RELEASE_URL="${CSWEB_RELEASE_URL:-https://csprousers.org/releases/8.1/csweb-8.1.2.zip}"
BOWER_REF="${BOWER_REF:-v1.1.0}"
PKG_DIR_NAME="csweb"

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

for cmd in git php zip unzip; do command -v "$cmd" >/dev/null || fail "'$cmd' is required"; done
if [ -z "${COMPOSER_BIN:-}" ]; then
    if command -v composer >/dev/null; then COMPOSER_BIN="composer";
    elif [ -f "$ROOT/composer.phar" ]; then COMPOSER_BIN="php $ROOT/composer.phar";
    else fail "composer not found (install it or put composer.phar at the repo root)"; fi
fi

cd "$ROOT"
COMMIT="$(git rev-parse --short "$REF")"
VERSION="$(git show "$REF:VERSION" 2>/dev/null | tr -d '[:space:]' || echo unknown)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
APP="$WORK/$PKG_DIR_NAME"
mkdir -p "$APP" "$OUT_DIR"

log "Exporting $REF ($COMMIT, version $VERSION)"
git archive "$REF" | tar -x -C "$APP"

log "Installing production PHP dependencies"
(
    cd "$APP"
    export COMPOSER_ALLOW_SUPERUSER=1
    # vitexsoftware/phpunit-skeleton-generator is a developer tool listed under
    # "require"; it drags PHPUnit (~400 MB) into production. Move it out of the
    # package when it is still there.
    if php -r 'exit(isset(json_decode(file_get_contents("composer.json"), true)["require"]["vitexsoftware/phpunit-skeleton-generator"]) ? 0 : 1);'; then
        $COMPOSER_BIN remove vitexsoftware/phpunit-skeleton-generator --update-no-dev --no-scripts --no-interaction --no-progress \
            || fail "could not drop the PHPUnit skeleton generator from the package"
    fi
    $COMPOSER_BIN install --no-dev --optimize-autoloader --no-scripts --no-interaction --no-progress \
        || fail "composer install failed (see the messages above)"
    find vendor -type d -name .git -prune -exec rm -rf {} +
    [ ! -d vendor/phpunit ] || fail "PHPUnit is still in vendor/ — check composer.json"
)

log "Adding front-end assets (bower_components)"
if [ -d "$ROOT/bower_components/bootstrap" ]; then
    cp -R "$ROOT/bower_components" "$APP/"
elif curl -fsSL -o "$WORK/upstream.zip" "$CSWEB_RELEASE_URL" 2>/dev/null; then
    unzip -q "$WORK/upstream.zip" 'bower_components/*' -d "$WORK/upstream"
    mv "$WORK/upstream/bower_components" "$APP/"
elif git cat-file -e "$BOWER_REF:bower_components" 2>/dev/null \
     || { git fetch -q origin "refs/tags/$BOWER_REF:refs/tags/$BOWER_REF" 2>/dev/null \
          && git cat-file -e "$BOWER_REF:bower_components" 2>/dev/null; }; then
    git archive "$BOWER_REF" bower_components | tar -x -C "$APP"
else
    fail "no source for bower_components (local folder, $CSWEB_RELEASE_URL, or tag $BOWER_REF)"
fi
for f in jquery/jquery.min.js bootstrap/js/bootstrap.bundle.min.js bootstrap/css/bootstrap.min.css \
         datatables/jquery.dataTables.min.js fontawesome-free/css/all.min.css jquery-easing/jquery.easing.min.js; do
    [ -f "$APP/bower_components/$f" ] || fail "missing front-end asset bower_components/$f"
done

log "Removing developer-only files"
(
    cd "$APP"
    rm -rf .github .dockerignore .env.example .env docker Dockerfile docker-compose*.yml docker-entrypoint.sh \
           tests phpunit.xml.dist phpstan.neon.dist phpstan-baseline.neon .php-cs-fixer.dist.php \
           docs docs-nextra nbproject COMMIT_MESSAGE.txt cleanup.sh cleanup.bat install.sh packages.txt \
           _config.yml web.config composer.phar tools build
    rm -f src/config.php   # written by the setup wizard; never ship one
)

log "Preparing writable folders"
(
    cd "$APP"
    mkdir -p var/cache var/logs files files_csweb
    # Some extractors drop empty folders: keep a placeholder in each one.
    for d in var/cache var/logs files files_csweb; do
        [ -n "$(ls -A "$d" 2>/dev/null)" ] || touch "$d/.keep"
    done
    # Shared hosts run PHP as the account owner: 755/644 is enough, never 777.
    find . -type d -exec chmod 755 {} +
    find . -type f -exec chmod 644 {} +
    chmod 755 bin/console 2>/dev/null || true
)

log "Adding the installation guide and build information"
for f in INSTALL-cPanel.md INSTALL-cPanel.pdf; do
    [ -f "$ROOT/tools/cpanel/$f" ] && cp "$ROOT/tools/cpanel/$f" "$APP/"
done
cat > "$APP/BUILD-INFO.txt" <<EOF
CSWeb Community — shared hosting package
Version : $VERSION
Commit  : $COMMIT ($(git log -1 --format=%cs "$REF"))
Built   : $(date -u +%Y-%m-%dT%H:%M:%SZ)
Requires: PHP 8.1+ (8.3 recommended), MySQL 8.0+ or MariaDB 10.6+, Apache with mod_rewrite
Guide   : INSTALL-cPanel.pdf
EOF

ZIP="$OUT_DIR/csweb-${VERSION}-cpanel.zip"
log "Creating $(basename "$ZIP")"
rm -f "$ZIP"
(cd "$WORK" && zip -qr -X "$ZIP" "$PKG_DIR_NAME")
(cd "$OUT_DIR" && { command -v sha256sum >/dev/null && sha256sum "$(basename "$ZIP")" || shasum -a 256 "$(basename "$ZIP")"; } > "$(basename "$ZIP").sha256")

log "Done"
printf '  %s (%s)\n  %s\n' "$ZIP" "$(du -h "$ZIP" | cut -f1)" "$(cat "$ZIP.sha256")"
printf '  %s files\n' "$(unzip -Z1 "$ZIP" | grep -vc '/$')"
