# ============================================================================
# CSWeb Community Platform - Production Dockerfile
# ============================================================================
# Author: Bouna DRAME
# Date: 14 Mars 2026
# Version: 1.0.0
#
# Multi-stage build for optimized production image
# ============================================================================

FROM php:8.3-apache-bookworm AS base

# Install system dependencies for ALL database drivers
RUN apt-get update && apt-get install -y \
    git \
    curl \
    cron \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    unzip \
    unixodbc-dev \
    gnupg2 \
    gettext-base \
    default-mysql-client \
    nodejs \
    npm \
    openssh-client \
    autossh \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Microsoft ODBC Driver for SQL Server (Debian 12 bookworm)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64,arm64,armhf signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions for MySQL and PostgreSQL
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mysqli \
    pgsql \
    mbstring \
    xml \
    zip \
    opcache

# Install PHP SQL Server extension (pdo_sqlsrv only — sqlsrv standalone not available on PHP 8.3)
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable pdo_sqlsrv

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies (--no-scripts car config.php est genere via /setup).
# Retry then fall back to git sources: GitHub's dist endpoint (codeload) can
# return transient HTTP 400/429 on anonymous rate limits, which --prefer-source
# avoids by cloning instead of downloading archives.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    || composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-source

# Install frontend dependencies (Bootstrap, FontAwesome, jQuery, etc.)
#
# CSWeb 8.1 ships its bower_components pre-built inside the release archive,
# in a flat layout the templates address directly:
#   bower_components/bootstrap/css/bootstrap.min.css
#   bower_components/fontawesome-free/css/all.min.css
#
# `bower install` resolves bower.json instead — Bootstrap 3 and font-awesome 4 —
# and produces a different tree (bootstrap/dist/css/, no fontawesome-free at
# all). Every stylesheet then 404s, which CSWeb turns into a redirect to
# /setup/, so the browser loops until ERR_TOO_MANY_REDIRECTS.
#
# Take the assets from the upstream archive, which is also what pins them to
# the exact CSWeb release. bower_components/ is gitignored, so this must not
# depend on anything present in the build context.
ARG CSWEB_RELEASE_URL=https://csprousers.org/releases/8.1/csweb-8.1.2.zip
RUN set -eux; \
    rm -rf /var/www/html/bower_components; \
    curl -fsSL -o /tmp/csweb-release.zip "$CSWEB_RELEASE_URL"; \
    unzip -q /tmp/csweb-release.zip 'bower_components/*' -d /tmp/csweb-release; \
    mv /tmp/csweb-release/bower_components /var/www/html/bower_components; \
    rm -rf /tmp/csweb-release.zip /tmp/csweb-release; \
    test -f /var/www/html/bower_components/bootstrap/css/bootstrap.min.css; \
    test -f /var/www/html/bower_components/fontawesome-free/css/all.min.css

# Set base permissions
#
# CSWeb 8.1 widened the writability check in setup/prereqs.php from
# (var, app/config, src/AppBundle) to (var, files, files_csweb, app/config,
# src/). files_csweb ships as an empty directory in the upstream archive, so
# git does not carry it and it has to be created here, or /setup fails its
# requirements check with no indication of which one.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/files \
    && mkdir -p /var/www/html/files_csweb \
    && mkdir -p /var/www/html/var/cache \
    && mkdir -p /var/www/html/var/logs \
    && chmod -R 775 /var/www/html/files /var/www/html/files_csweb \
    && chown -R www-data:www-data /var/www/html/files /var/www/html/files_csweb \
    && chmod -R 777 /var/www/html/var

# Configure cron for breakout scheduler + backup (runs every minute)
RUN echo "* * * * * www-data /usr/local/bin/php /var/www/html/bin/console csweb:scheduler-run >> /var/www/html/var/logs/scheduler-cron.log 2>&1" \
    > /etc/cron.d/csweb-scheduler \
    && echo "* * * * * www-data /usr/local/bin/php /var/www/html/bin/console csweb:backup-run >> /var/www/html/var/logs/backup-cron.log 2>&1" \
    >> /etc/cron.d/csweb-scheduler \
    && chmod 0644 /etc/cron.d/csweb-scheduler \
    && crontab -u www-data /etc/cron.d/csweb-scheduler || true

# Copy and set entrypoint (auto: permissions, cache clear)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -f http://localhost/api/ || exit 1

# Entrypoint handles permissions + cache, then starts Apache
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
