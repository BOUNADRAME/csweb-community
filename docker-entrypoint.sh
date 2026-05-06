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

# ============================================================================
# Persist config.php across container recreations
# ============================================================================
CONFIG_SRC="/var/www/html/src/AppBundle/config.php"
CONFIG_PERSIST="/var/www/html/config-persist/config.php"

mkdir -p /var/www/html/config-persist

if [ -f "$CONFIG_SRC" ] && [ ! -L "$CONFIG_SRC" ] && [ ! -f "$CONFIG_PERSIST" ]; then
    # First run after setup: config.php exists but not yet persisted — save it
    cp "$CONFIG_SRC" "$CONFIG_PERSIST"
    rm -f "$CONFIG_SRC"
    ln -s "$CONFIG_PERSIST" "$CONFIG_SRC"
    echo "[CSWeb] config.php persisted to volume."
elif [ -f "$CONFIG_PERSIST" ]; then
    # Container recreated: restore symlink from persisted config
    rm -f "$CONFIG_SRC"
    ln -s "$CONFIG_PERSIST" "$CONFIG_SRC"
    echo "[CSWeb] config.php restored from volume."
fi

chown -R www-data:www-data /var/www/html/config-persist

# Clear Symfony cache if config.php exists (app is configured)
if [ -f /var/www/html/src/AppBundle/config.php ]; then
    echo "[CSWeb] config.php found, clearing Symfony cache..."
    rm -rf /var/www/html/var/cache/*
    su -s /bin/bash www-data -c "php /var/www/html/bin/console cache:warmup --env=prod --no-debug" 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/var
    chmod -R 777 /var/www/html/var
    echo "[CSWeb] Cache cleared successfully."

    # Remove UNIQUE constraint on schema_name (required for multi-dictionary breakout)
    echo "[CSWeb] Checking schema_name constraint..."
    php -r "
        require '/var/www/html/src/AppBundle/config.php';
        try {
            \$port = defined('DBPORT') ? DBPORT : '3306';
            \$pdo = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';port=' . \$port, DBUSER, DBPASS);
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

    # Register dashboard_all permission (id 11) — idempotent
    echo "[CSWeb] Checking dashboard_all permission..."
    php -r "
        require '/var/www/html/src/AppBundle/config.php';
        try {
            \$port = defined('DBPORT') ? DBPORT : '3306';
            \$pdo = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';port=' . \$port, DBUSER, DBPASS);
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$pdo->exec(\"INSERT IGNORE INTO cspro_permissions (id, name, modified_time, created_time) VALUES (11, 'dashboard_all', NOW(), NOW())\");
            echo '[CSWeb] dashboard_all permission registered' . PHP_EOL;
        } catch (Exception \$e) {
            echo '[CSWeb] Could not register dashboard_all permission: ' . \$e->getMessage() . PHP_EOL;
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
# Breakout target SSH tunnel (BREAKOUT_CONNECTION_MODE=tunnel)
# ============================================================================
BREAKOUT_CONNECTION_MODE=${BREAKOUT_CONNECTION_MODE:-direct}
if [ "$BREAKOUT_CONNECTION_MODE" = "tunnel" ]; then
    echo "[CSWeb] Connection mode: tunnel — preparing SSH forward..."

    KEY_PATH="/run/secrets/breakout_ssh_key"

    # /dev/null is the default mount when BREAKOUT_SSH_KEY_PATH_HOST is unset
    # in .env. Treat both "missing" and "empty" as fail-fast.
    if [ ! -s "$KEY_PATH" ]; then
        echo "[CSWeb] ERROR: BREAKOUT_CONNECTION_MODE=tunnel requires a real SSH"
        echo "[CSWeb]        private key. Set BREAKOUT_SSH_KEY_PATH_HOST in .env"
        echo "[CSWeb]        to the host path of your private key (e.g. /Users/me/.ssh/id_breakout)."
        exit 1
    fi

    if [ -z "$BREAKOUT_SSH_HOST" ] || [ -z "$BREAKOUT_SSH_USER" ]; then
        echo "[CSWeb] ERROR: BREAKOUT_SSH_HOST and BREAKOUT_SSH_USER must be set"
        echo "[CSWeb]        when BREAKOUT_CONNECTION_MODE=tunnel."
        exit 1
    fi

    # Copy the key to a writable location so we can chmod 600 (the bind mount
    # might be on a filesystem where chmod is a no-op, e.g. macOS Docker Desktop).
    install -m 600 "$KEY_PATH" /tmp/breakout_ssh_key
    chown www-data:www-data /tmp/breakout_ssh_key

    # known_hosts: accept the remote host on first connect (we lose host-key
    # pinning, but the alternative is an interactive prompt that breaks startup).
    # Operators wanting strict checking can pre-populate /home/www-data/.ssh/known_hosts.
    mkdir -p /home/www-data/.ssh
    chown -R www-data:www-data /home/www-data/.ssh
    chmod 700 /home/www-data/.ssh

    BREAKOUT_SSH_PORT=${BREAKOUT_SSH_PORT:-22}
    BREAKOUT_TUNNEL_LOCAL_PORT=${BREAKOUT_TUNNEL_LOCAL_PORT:-13306}
    BREAKOUT_TUNNEL_REMOTE_HOST=${BREAKOUT_TUNNEL_REMOTE_HOST:-127.0.0.1}
    BREAKOUT_TUNNEL_REMOTE_PORT=${BREAKOUT_TUNNEL_REMOTE_PORT:-3306}
    BREAKOUT_TUNNEL_KEEPALIVE=${BREAKOUT_TUNNEL_KEEPALIVE:-30}

    # autossh -M 0 disables its own monitor port and relies on SSH ServerAlive.
    # -f forks to background, -N disables remote command (port-forward only).
    AUTOSSH_GATETIME=0 \
    autossh -M 0 -f -N \
        -o "ServerAliveInterval=${BREAKOUT_TUNNEL_KEEPALIVE}" \
        -o "ServerAliveCountMax=3" \
        -o "ExitOnForwardFailure=yes" \
        -o "StrictHostKeyChecking=accept-new" \
        -o "UserKnownHostsFile=/home/www-data/.ssh/known_hosts" \
        -i /tmp/breakout_ssh_key \
        -L "0.0.0.0:${BREAKOUT_TUNNEL_LOCAL_PORT}:${BREAKOUT_TUNNEL_REMOTE_HOST}:${BREAKOUT_TUNNEL_REMOTE_PORT}" \
        -p "${BREAKOUT_SSH_PORT}" \
        "${BREAKOUT_SSH_USER}@${BREAKOUT_SSH_HOST}" \
        && echo "[CSWeb] SSH tunnel up: 127.0.0.1:${BREAKOUT_TUNNEL_LOCAL_PORT} → ${BREAKOUT_SSH_HOST}:${BREAKOUT_TUNNEL_REMOTE_PORT}" \
        || echo "[CSWeb] WARN: autossh failed to start (CSWeb will run but breakout queries will time out until tunnel is up)"
else
    echo "[CSWeb] Connection mode: direct — skipping SSH tunnel setup."
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

# Final permission fix — ensure var/ is fully owned by www-data before Apache starts
chown -R www-data:www-data /var/www/html/var
chmod -R 777 /var/www/html/var

echo "[CSWeb] Initialization complete. Starting Apache..."

# Execute the original CMD (apache2-foreground)
exec "$@"
