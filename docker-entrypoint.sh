#!/bin/bash
# ============================================================================
# CSWeb Community Platform - Docker Entrypoint
# ============================================================================
# Executed at container startup before Apache
# Handles: permissions, cache clearing, directory creation
# ============================================================================

set -e

echo "[CSWeb] Starting container initialization..."

# Ensure required directories exist with correct permissions
mkdir -p /var/www/html/var/cache
mkdir -p /var/www/html/var/logs
mkdir -p /var/www/html/files

# Fix permissions for writable directories
chown -R www-data:www-data /var/www/html/var
chown -R www-data:www-data /var/www/html/files
chmod -R 777 /var/www/html/var
chmod -R 775 /var/www/html/files

# Clear Symfony cache if config.php exists (app is configured)
if [ -f /var/www/html/src/AppBundle/config.php ]; then
    echo "[CSWeb] config.php found, clearing Symfony cache..."
    php bin/console cache:clear --no-warmup 2>/dev/null || true
    php bin/console cache:warmup 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/var
    chmod -R 777 /var/www/html/var
    echo "[CSWeb] Cache cleared successfully."

    # Remove UNIQUE constraint on schema_name (required for multi-dictionary breakout)
    echo "[CSWeb] Checking schema_name constraint..."
    php -r "
        require '/var/www/html/src/AppBundle/config.php';
        try {
            \$pdo = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';port=' . DBPORT, DBUSER, DBPASS);
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$result = \$pdo->query(\"SHOW INDEX FROM cspro_dictionaries_schema WHERE Key_name = 'schema_name'\");
            if (\$result->rowCount() > 0) {
                \$pdo->exec('ALTER TABLE cspro_dictionaries_schema DROP KEY schema_name');
                echo '[CSWeb] Dropped UNIQUE constraint on schema_name' . PHP_EOL;
            } else {
                echo '[CSWeb] schema_name constraint already removed' . PHP_EOL;
            }
        } catch (Exception \$e) {
            echo '[CSWeb] Could not check/drop schema_name constraint: ' . \$e->getMessage() . PHP_EOL;
        }
    " 2>/dev/null || true
else
    echo "[CSWeb] config.php not found, skipping cache clear (run /setup first)."
fi

# Start cron daemon for breakout scheduler
if [ -f /etc/cron.d/csweb-scheduler ]; then
    echo "[CSWeb] Starting cron daemon for breakout scheduler..."
    cron
    echo "[CSWeb] Cron daemon started."
fi

# ============================================================================
# Apply environment-based configuration via envsubst
# ============================================================================
echo "[CSWeb] Applying environment configuration..."

# Define variables to substitute (avoid replacing Apache's own ${APACHE_LOG_DIR})
ENVSUBST_VARS='${PHP_MEMORY_LIMIT} ${PHP_MAX_EXECUTION_TIME} ${PHP_MAX_INPUT_TIME} ${PHP_UPLOAD_MAX_FILESIZE} ${PHP_POST_MAX_SIZE} ${PHP_SESSION_GC_MAXLIFETIME} ${PHP_OPCACHE_MEMORY} ${PHP_OPCACHE_MAX_FILES} ${APACHE_TIMEOUT} ${APACHE_KEEP_ALIVE_TIMEOUT} ${APACHE_MAX_KEEP_ALIVE_REQUESTS} ${APACHE_MAX_REQUEST_WORKERS} ${APACHE_SERVER_LIMIT}'

# Set defaults if not provided
: "${PHP_MEMORY_LIMIT:=512M}"
: "${PHP_MAX_EXECUTION_TIME:=300}"
: "${PHP_MAX_INPUT_TIME:=300}"
: "${PHP_UPLOAD_MAX_FILESIZE:=100M}"
: "${PHP_POST_MAX_SIZE:=100M}"
: "${PHP_SESSION_GC_MAXLIFETIME:=7200}"
: "${PHP_OPCACHE_MEMORY:=128}"
: "${PHP_OPCACHE_MAX_FILES:=10000}"
: "${APACHE_TIMEOUT:=300}"
: "${APACHE_KEEP_ALIVE_TIMEOUT:=5}"
: "${APACHE_MAX_KEEP_ALIVE_REQUESTS:=100}"
: "${APACHE_MAX_REQUEST_WORKERS:=150}"
: "${APACHE_SERVER_LIMIT:=150}"

export PHP_MEMORY_LIMIT PHP_MAX_EXECUTION_TIME PHP_MAX_INPUT_TIME PHP_UPLOAD_MAX_FILESIZE PHP_POST_MAX_SIZE PHP_SESSION_GC_MAXLIFETIME PHP_OPCACHE_MEMORY PHP_OPCACHE_MAX_FILES APACHE_TIMEOUT APACHE_KEEP_ALIVE_TIMEOUT APACHE_MAX_KEEP_ALIVE_REQUESTS APACHE_MAX_REQUEST_WORKERS APACHE_SERVER_LIMIT

# PHP config
if [ -f /etc/templates/csweb.ini ]; then
    envsubst "$ENVSUBST_VARS" < /etc/templates/csweb.ini > /usr/local/etc/php/conf.d/csweb.ini
    echo "[CSWeb] PHP config applied (memory_limit=${PHP_MEMORY_LIMIT}, max_execution_time=${PHP_MAX_EXECUTION_TIME})"
fi

# Apache VirtualHost config
if [ -f /etc/templates/000-default.conf ]; then
    envsubst "$ENVSUBST_VARS" < /etc/templates/000-default.conf > /etc/apache2/sites-available/000-default.conf
    echo "[CSWeb] Apache VirtualHost config applied"
fi

# Apache MPM Prefork config
if [ -f /etc/templates/mpm_prefork.conf ]; then
    envsubst "$ENVSUBST_VARS" < /etc/templates/mpm_prefork.conf > /etc/apache2/mods-available/mpm_prefork.conf
    echo "[CSWeb] Apache MPM config applied (MaxRequestWorkers=${APACHE_MAX_REQUEST_WORKERS})"
fi

echo "[CSWeb] Environment configuration applied."

echo "[CSWeb] Initialization complete. Starting Apache..."

# Execute the original CMD (apache2-foreground)
exec "$@"
