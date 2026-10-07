#!/usr/bin/env bash
# ──────────────────────────────────────────────
# Container entrypoint.
# State (storage, .env, database) lives on host bind mounts.
# NOTE: php artisan serve is a development server.
# Use nginx + php-fpm (or Octane) for real deployments.
# ──────────────────────────────────────────────
set -euo pipefail

# ──────────────────────────────────────────────
# Helpers
# ──────────────────────────────────────────────
log()  { echo "[entrypoint] $*"; }
fail() { echo "[entrypoint] ERROR: $*" >&2; exit 1; }

MAX_DB_RETRIES="${MAX_DB_RETRIES:-30}"
RETRY_INTERVAL="${RETRY_INTERVAL:-3}"

# ──────────────────────────────────────────────
# Pre-flight checks
# ──────────────────────────────────────────────
[ -f .env ] || fail ".env not found — bind-mount it or create it on the host"

# Bind mounts are NOT seeded from the image: make sure the storage skeleton exists.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# ──────────────────────────────────────────────
# Application key (written to the bind-mounted .env, so it persists)
# ──────────────────────────────────────────────
if ! grep -q "^APP_KEY=." .env; then
    log "Generating application key..."
    php artisan key:generate --force
fi

# ──────────────────────────────────────────────
# Wait for database (with timeout)
# ──────────────────────────────────────────────
log "Waiting for database at ${DB_HOST:-mariadb}..."
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
# One-time setup.
# Runs BEFORE config:cache so the QUEUE_CONNECTION override below takes effect.
# The sentinel is only written once the critical import has succeeded; a failed
# import aborts the container (via `set -e`) so it is retried on the next boot.
# ──────────────────────────────────────────────
if [ ! -f storage/.setup_complete ]; then
    log "Running initial data import..."
    # Critical: run synchronously so it completes before we continue.
    QUEUE_CONNECTION=sync php artisan import:airlines
    # Optional: only applies when the WSSS airport is present in the database.
    php artisan seed:wsss-bays || log "WARNING: seed:wsss-bays skipped (WSSS airport not present)"
    touch storage/.setup_complete
fi

# ──────────────────────────────────────────────
# Cache config/routes/views for production
# ──────────────────────────────────────────────
log "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ──────────────────────────────────────────────
# Start server (exec replaces shell — proper PID 1)
# ──────────────────────────────────────────────
log "Starting Laravel development server on port 80..."
exec php artisan serve --host=0.0.0.0 --port=80
