@echo off
REM ============================================================================
REM CSWeb Community Platform - Cleanup Script (Windows)
REM ============================================================================
REM Stops all containers, removes volumes, and cleans up config files
REM Usage: cleanup.bat
REM ============================================================================

echo ============================================
echo  CSWeb Cleanup
echo ============================================

REM Stop and remove all containers + volumes
echo [1/4] Stopping containers and removing volumes...
docker compose --profile local-postgres --profile local-mysql --profile local-sqlserver --profile dev down -v --remove-orphans 2>nul

REM Remove config.php
echo [2/4] Removing config.php...
del /f /q src\AppBundle\config.php 2>nul

REM Clear cache and logs
echo [3/4] Clearing cache and logs...
if exist var\cache rd /s /q var\cache
mkdir var\cache
if exist var\logs rd /s /q var\logs
mkdir var\logs

REM Remove orphan volumes
echo [4/4] Removing orphan csweb volumes...
for /f "tokens=*" %%v in ('docker volume ls -q --filter "name=csweb" 2^>nul') do (
    docker volume rm %%v 2>nul
)

echo.
echo ============================================
echo  Cleanup complete!
echo  To restart: docker compose --profile local-postgres up -d --build
echo ============================================
