FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Node.js (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

    # Create startup script inline
    RUN printf '#!/bin/bash\nset -e\n\n# Install composer dependencies if missing\nif [ ! -f "vendor/autoload.php" ]; then\n    echo "Installing Composer dependencies..."\n    composer install --no-interaction\nfi\n\n# Generate application key if not set\nif ! grep -q "^APP_KEY=." .env 2>/dev/null; then\n    echo "Generating application key..."\n    php artisan key:generate --force\nfi\n\n# Wait for database to be ready\necho "Waiting for database..."\nuntil php artisan db:show > /dev/null 2>&1; do\n    sleep 3\ndone\necho "Database is ready."\n\n# Run migrations\necho "Running migrations..."\nphp artisan migrate --force\n\n# Create storage link\nphp artisan storage:link --force\n\n# Install and build frontend assets\necho "Installing npm dependencies..."\nnpm ci\necho "Building frontend assets..."\nnpm run dev\n\n# Run one-time setup commands\nif [ ! -f ".setup_complete" ]; then\n    echo "Running initial data import..."\n    php artisan import:airlines\n    php artisan seed:wsss-bays\n    touch .setup_complete\nfi\n\n# Start the Laravel development server\nphp artisan serve --host=0.0.0.0 --port=80\n' > /usr/local/bin/start.sh \
        && chmod +x /usr/local/bin/start.sh

# Expose port 80
EXPOSE 80

# Start Laravel development server
CMD ["/usr/local/bin/start.sh"]
