# ============================================================================
# CSWeb Community Platform - Production Dockerfile
# ============================================================================
# Author: Bouna DRAME
#
# Four stages, ordered so that a code change rebuilds in seconds:
#   php-ext  PHP extensions, compiled once and reused from the build cache
#   vendor   Composer dependencies (reinstalled only when composer.lock changes)
#   assets   front-end files from npm tarballs (no Node.js, no bower)
#   runtime  the image that runs: runtime libraries only, no compiler,
#            no Node.js, no -dev packages
#
# Build arguments (docker-compose.yml passes them from .env):
#   WITH_SQLSERVER=1  include the SQL Server driver (default). 0 skips the
#                     Microsoft repository: useful on filtered networks.
# ============================================================================

ARG PHP_IMAGE=php:8.3-apache-bookworm

# ---------------------------------------------------------------------------
# 1. PHP extensions
# ---------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS php-ext

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
    && apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unixodbc-dev

# pdo, mbstring and xml are already compiled into the official image.
# Only pdo_sqlsrv is used for SQL Server (DBAL and Breakout Health go
# through PDO), so the standalone sqlsrv extension is not compiled.
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql mysqli pgsql zip opcache \
    && MAKEFLAGS="-j$(nproc)" pecl install pdo_sqlsrv \
    && docker-php-ext-enable pdo_sqlsrv \
    && rm -rf /tmp/pear

# ---------------------------------------------------------------------------
# 2. Composer dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app

# Dependencies first: this layer is reused until composer.lock changes.
# Platform checks are skipped here because this image lacks the runtime's
# extensions (pgsql, sqlsrv...); the runtime stage has them. On a GitHub
# rate limit (HTTP 400/429), fall back to cloning the sources.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs \
    || composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-source --ignore-platform-reqs

# Then the code, for the optimised class map (a few seconds).
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts --ignore-platform-reqs

# ---------------------------------------------------------------------------
# 3. Front-end files used by the pages, from npm tarballs (checksums pinned).
#    Same paths as the former bower_components/ layout.
# ---------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS assets
RUN set -eu; \
    out=/out/bower_components; mkdir -p "$out"; cd /tmp; \
    fetch() { \
        curl -fsSL --retry 3 "https://registry.npmjs.org/$1/-/$2-$3.tgz" -o "$2.tgz"; \
        echo "$4  $2.tgz" | sha256sum -c - >/dev/null; \
        rm -rf "$2" && mkdir "$2" && tar xzf "$2.tgz" -C "$2" --strip-components=1; \
    }; \
    fetch jquery             jquery             3.7.1   68a9f787516da47c680e09c187bcbac4536b6f85d90eb882844e12919e583f53; \
    fetch datatables.net     datatables.net     1.10.21 e679104d5884cf2a2a60be2cc61c77d8f45db28d6070a47440d5a50aa6fee765; \
    fetch datatables.net-dt  datatables.net-dt  1.10.21 df96bdd3ae701ffc3e56c1277b4a6822d072d9c651abe383cfaa8e43b0e22a58; \
    fetch datatables.net-bs4 datatables.net-bs4 1.10.21 a804689048645e77eda1cca508244ba078b80ff442be1ac93eea005b6142df63; \
    fetch bootstrap          bootstrap          3.3.7   a9de9f9d74b46743821bda60e5f81233c7a2dc83ad4db60d0c0e396dc255fc2a; \
    mkdir -p "$out/jquery/dist" "$out/datatables/media/js" "$out/datatables/media/css" "$out/bootstrap/dist"; \
    cp jquery/dist/jquery.min.js                  "$out/jquery/dist/"; \
    cp datatables.net/js/jquery.dataTables.min.js "$out/datatables/media/js/"; \
    cp datatables.net-bs4/js/*.min.js             "$out/datatables/media/js/"; \
    cp datatables.net-dt/css/*.min.css datatables.net-bs4/css/*.min.css "$out/datatables/media/css/"; \
    cp -r datatables.net-dt/images                "$out/datatables/media/"; \
    cp -r bootstrap/dist/css bootstrap/dist/fonts "$out/bootstrap/dist/"

# ---------------------------------------------------------------------------
# 4. Runtime image
# ---------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS runtime
ARG WITH_SQLSERVER=1

# Runtime libraries only. mysql client: backups (mysqldump); gettext-base:
# envsubst in the entrypoint; openssh-client/autossh: tunnel mode.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        cron curl ca-certificates gettext-base default-mysql-client \
        openssh-client autossh libpq5 libzip4 \
    && if [ "$WITH_SQLSERVER" = "1" ]; then \
        curl -fsSL https://packages.microsoft.com/keys/microsoft.asc -o /usr/share/keyrings/microsoft-prod.asc \
        && echo "deb [arch=amd64,arm64,armhf signed-by=/usr/share/keyrings/microsoft-prod.asc] https://packages.microsoft.com/debian/12/prod bookworm main" \
            > /etc/apt/sources.list.d/mssql-release.list \
        && apt-get update \
        && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 unixodbc; \
    fi

# Compiled extensions from stage 1 (pdo_sqlsrv stays disabled without its driver).
COPY --from=php-ext /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-ext /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN if [ "$WITH_SQLSERVER" != "1" ]; then rm -f /usr/local/etc/php/conf.d/docker-php-ext-pdo_sqlsrv.ini; fi \
    && php -m | grep -qi pdo_mysql \
    && a2enmod rewrite headers ssl

# Cron for the breakout scheduler and backups (runs every minute). One copy
# in /etc/cron.d, which carries the user field: a second copy as www-data's
# crontab used to fail every minute with "www-data: not found".
RUN echo "* * * * * www-data /usr/local/bin/php /var/www/html/bin/console csweb:scheduler-run >> /var/www/html/var/logs/scheduler-cron.log 2>&1" \
        > /etc/cron.d/csweb-scheduler \
    && echo "* * * * * www-data /usr/local/bin/php /var/www/html/bin/console csweb:backup-run >> /var/www/html/var/logs/backup-cron.log 2>&1" \
        >> /etc/cron.d/csweb-scheduler \
    && echo "*/2 * * * * www-data /usr/local/bin/php /var/www/html/bin/console csweb:breakout-metrics >> /var/www/html/var/logs/breakout-metrics-cron.log 2>&1" \
        >> /etc/cron.d/csweb-scheduler \
    && chmod 0644 /etc/cron.d/csweb-scheduler

COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

# Front-end files and dependencies first (they change rarely), the code last.
# --chown at copy time: no second copy of every file in a "chown -R" layer.
COPY --chown=www-data:www-data --from=assets /out/bower_components ./bower_components
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
RUN mkdir -p files var/cache var/logs \
    && chown www-data:www-data files var var/cache var/logs \
    && chmod 775 files \
    && chmod 777 var var/cache var/logs

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD c=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/api/server); [ "$c" = 200 ] || [ "$c" = 401 ] || { [ ! -f /var/www/html/src/AppBundle/config.php ] && curl -fsS -o /dev/null http://localhost/setup/; } || exit 1

# Entrypoint handles permissions + cache, then starts Apache
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
