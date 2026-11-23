# ============================================================================
# CSWeb Community Platform - Production Dockerfile
# ============================================================================
# Author: Bouna DRAME
#
# Four stages, ordered so that a code change rebuilds in seconds:
#   php-ext  PHP extensions, compiled once and reused from the build cache
#   vendor   Composer dependencies (reinstalled only when composer.lock changes)
#   assets   front-end files from the CSWeb 8.1 release archive (cached)
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
# 3. Front-end files
#
# CSWeb 8.1 ships its bower_components pre-built inside the release archive,
# in a flat layout the templates address directly
# (bower_components/bootstrap/css/..., bower_components/fontawesome-free/...).
# `bower install` would resolve bower.json instead (Bootstrap 3, font-awesome
# 4) and every stylesheet would 404, which CSWeb turns into a redirect loop to
# /setup/. Taking them from the upstream archive also pins them to the exact
# CSWeb release. This stage is cached: the archive is downloaded once.
# ---------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS assets
ARG CSWEB_RELEASE_URL=https://csprousers.org/releases/8.1/csweb-8.1.2.zip
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    rm -f /etc/apt/apt.conf.d/docker-clean \
    && apt-get update && apt-get install -y --no-install-recommends unzip \
    && curl -fsSL --retry 3 -o /tmp/csweb-release.zip "$CSWEB_RELEASE_URL" \
    && unzip -q /tmp/csweb-release.zip 'bower_components/*' -d /out \
    && rm -f /tmp/csweb-release.zip \
    && test -f /out/bower_components/bootstrap/css/bootstrap.min.css \
    && test -f /out/bower_components/fontawesome-free/css/all.min.css

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
    && chmod 0644 /etc/cron.d/csweb-scheduler

COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

# Front-end files and dependencies first (they change rarely), the code last.
# --chown at copy time: no second copy of every file in a "chown -R" layer.
COPY --chown=www-data:www-data --from=assets /out/bower_components ./bower_components
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
# files_csweb ships as an empty directory upstream, so git does not carry it;
# /setup checks that it is writable.
RUN mkdir -p files files_csweb var/cache var/logs \
    && chown www-data:www-data files files_csweb var var/cache var/logs \
    && chmod 775 files files_csweb \
    && chmod 777 var var/cache var/logs

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD c=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/api/server); [ "$c" = 200 ] || [ "$c" = 401 ] || { [ ! -f /var/www/html/src/config.php ] && curl -fsS -o /dev/null http://localhost/setup/; } || exit 1

# Entrypoint handles permissions + cache, then starts Apache
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
