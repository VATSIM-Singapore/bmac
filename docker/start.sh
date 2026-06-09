#!/usr/bin/env bash
# ──────────────────────────────────────────────
# Laravel development container entrypoint.
# NOTE: php artisan serve is NOT suitable for production.
# Use nginx + php-fpm for real deployments.
# ──────────────────────────────────────────────
set -euo pipefail

# ──────────────────────────────────────────────
# Helpers
# ──────────────────────────────────────────────
log()  { echo "[entrypoint] $*"; }
fail() { echo "[entrypoint] ERROR: $*" >&2; exit 1; }

MAX_DB_RETRIES=30
RETRY_INTERVAL=3

# ──────────────────────────────────────────────
# Pre-flight checks
# ──────────────────────────────────────────────
[ -f .env ] || fail ".env file not found"

# ──────────────────────────────────────────────
# Composer dependencies
# ──────────────────────────────────────────────
if [ ! -f vendor/autoload.php ]; then
    log "Installing Composer dependencies..."
    composer install --no-interaction --no-progress --prefer-dist
fi

# ──────────────────────────────────────────────
# Application key
# ──────────────────────────────────────────────
if ! grep -q "^APP_KEY=." .env; then
    log "Generating application key..."
    php artisan key:generate --force
fi

# ──────────────────────────────────────────────
# Wait for database (with timeout)
# ──────────────────────────────────────────────
log "Waiting for database..."
count=0
until php artisan db:show > /dev/null 2>&1; do
    count=$((count + 1))
    if [ "$count" -ge "$MAX_DB_RETRIES" ]; then
        fail "Database not ready after $((MAX_DB_RETRIES * RETRY_INTERVAL))s — aborting"
    fi
    log "  Database not ready, retrying in ${RETRY_INTERVAL}s... ($count/$MAX_DB_RETRIES)"
    sleep "$RETRY_INTERVAL"
done
log "Database is ready."

# ──────────────────────────────────────────────
# Migrations & storage link
# ──────────────────────────────────────────────
log "Running migrations..."
php artisan migrate --force

if [ ! -L public/storage ]; then
    log "Creating storage link..."
    php artisan storage:link --force
fi

# ──────────────────────────────────────────────
# Frontend assets
# ──────────────────────────────────────────────
if [ ! -d node_modules ]; then
    log "Installing npm dependencies..."
    npm ci
fi

log "Building frontend assets..."
npm run dev

# ──────────────────────────────────────────────
# One-time setup
# ──────────────────────────────────────────────
if [ ! -f .setup_complete ]; then
    log "Running initial data import..."
    php artisan import:airlines
    php artisan seed:wsss-bays
    touch .setup_complete
fi

# ──────────────────────────────────────────────
# Start server (exec replaces shell — proper PID 1)
# ──────────────────────────────────────────────
log "Starting Laravel development server on port 80..."
exec php artisan serve --host=0.0.0.0 --port=80