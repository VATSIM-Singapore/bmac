FROM php:8.3-cli

# ──────────────────────────────────────────────
# System dependencies + PHP extensions
# ──────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ──────────────────────────────────────────────
# Node.js (LTS 20.x)
# ──────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ──────────────────────────────────────────────
# Composer (from official image)
# ──────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ──────────────────────────────────────────────
# Application setup
# ──────────────────────────────────────────────
WORKDIR /var/www/html

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# ──────────────────────────────────────────────
# Health check — confirms Laravel responds
# ──────────────────────────────────────────────
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:80/ || exit 1

EXPOSE 80

# NOTE: php artisan serve is a development server only.
# Use a production-grade server (nginx + php-fpm) for real deployments.
CMD ["/usr/local/bin/start.sh"]