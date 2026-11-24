#!/bin/bash
# ============================================================================
# CSWeb Community Platform - Cleanup Script (macOS / Linux)
# ============================================================================
# Stops all containers, removes volumes, and cleans up config files
# Usage: ./cleanup.sh
# ============================================================================

set -e

echo "============================================"
echo " CSWeb Cleanup"
echo "============================================"

# Stop and remove all containers + volumes
echo "[1/5] Stopping containers and removing volumes..."
docker compose --profile local-postgres --profile local-mysql --profile local-sqlserver --profile dev down -v --remove-orphans 2>/dev/null || true

# Remove config.php (local + persisted in Docker volume)
echo "[2/5] Removing config.php..."
rm -f src/AppBundle/config.php
docker volume rm csweb_config 2>/dev/null || true

# Clear cache and logs
echo "[3/5] Clearing cache and logs..."
rm -rf var/cache/* 2>/dev/null || true
rm -rf var/logs/* 2>/dev/null || true

# Remove dependencies (rebuilt automatically by Docker)
echo "[4/5] Removing vendor/ and bower_components/..."
rm -rf vendor/ bower_components/

# Remove orphan volumes
echo "[5/5] Removing orphan csweb volumes..."
docker volume ls -q --filter "name=csweb" 2>/dev/null | while read vol; do
    docker volume rm "$vol" 2>/dev/null || true
done

echo ""
echo "============================================"
echo " Cleanup complete!"
echo " Run: docker compose up -d csweb mysql --build"
echo "============================================"
