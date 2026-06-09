#!/bin/bash
set -e

# Install composer dependencies if missing
if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction
fi

# Generate application key if not set
if ! grep -q "^APP_KEY=." .env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Wait for database to be ready
echo "Waiting for database..."
until php artisan db:show > /dev/null 2>&1; do
    sleep 3
done
echo "Database is ready."

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Create storage link
php artisan storage:link --force

# Install and build frontend assets
echo "Installing npm dependencies..."
npm ci
echo "Building frontend assets..."
npm run dev

# Run one-time setup commands
if [ ! -f ".setup_complete" ]; then
    echo "Running initial data import..."
    php artisan import:airlines
    php artisan seed:wsss-bays
    touch .setup_complete
fi

# Start the Laravel development server
php artisan serve --host=0.0.0.0 --port=80