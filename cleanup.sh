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
echo "[1/4] Stopping containers and removing volumes..."
docker compose --profile local-postgres --profile local-mysql --profile local-sqlserver --profile dev down -v --remove-orphans 2>/dev/null || true

# Remove config.php
echo "[2/4] Removing config.php..."
rm -f src/AppBundle/config.php

# Clear cache and logs
echo "[3/4] Clearing cache and logs..."
rm -rf var/cache/* 2>/dev/null || true
rm -rf var/logs/* 2>/dev/null || true

# Remove orphan volumes
echo "[4/4] Removing orphan csweb volumes..."
docker volume ls -q --filter "name=csweb" 2>/dev/null | while read vol; do
    docker volume rm "$vol" 2>/dev/null || true
done

echo ""
echo "============================================"
echo " Cleanup complete!"
echo " To restart: docker compose --profile local-postgres up -d --build"
echo "============================================"
