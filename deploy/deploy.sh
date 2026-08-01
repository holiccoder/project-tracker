#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/www/wwwroot/project-tracker}"
WEB_USER="${WEB_USER:-www}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
LOCK_FILE="/tmp/project-tracker-deploy.lock"

cd "$PROJECT_DIR"

exec 9>"$LOCK_FILE"
flock -n 9 || { echo "deploy already running, aborting"; exit 1; }

echo "[1/7] pulling latest code"
git pull --ff-only origin main

echo "[2/7] installing composer dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --prefer-dist --no-interaction

if [ ! -f .env ]; then
    echo "[*] creating .env from server-local .env.production"
    cp .env.production .env
fi

echo "[3/7] running database migrations"
"$PHP_BIN" artisan migrate --force

echo "[4/7] building frontend assets"
"$NPM_BIN" ci --no-audit --no-fund
"$NPM_BIN" run build

echo "[5/7] caching config, routes and views"
"$PHP_BIN" artisan optimize

if [ ! -L public/storage ]; then
    echo "[*] creating storage symlink"
    "$PHP_BIN" artisan storage:link
fi

echo "[6/7] fixing permissions"
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R u+rwX,go+rX storage bootstrap/cache

echo "[7/7] restarting queue workers"
"$PHP_BIN" artisan queue:restart || true

echo "deploy finished"
