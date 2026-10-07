# syntax=docker/dockerfile:1

# ──────────────────────────────────────────────
# Production image for BMAC.
# Builds PHP deps + frontend assets and bakes the
# application code into the image, so a deployment
# only needs to `docker compose pull`.
#
# Runtime state (storage, .env, database) is NOT in
# this image — it is provided by host bind mounts in
# docker-compose.yml.
# ──────────────────────────────────────────────

# ──────────────────────────────────────────────
# Stage 1 — Composer dependencies
# ──────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The floating composer:2 image tracks the newest PHP (currently 8.5), while the
# app runs on php:8.3-cli. Platform checks here compare against the wrong PHP and
# the composer image lacks ext-gd/ext-pcntl, so skip them: this stage only produces
# vendor/ which is executed by the runtime stage that has the correct PHP + extensions.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
        --ignore-platform-reqs

# ──────────────────────────────────────────────
# Stage 2 — Frontend assets (Laravel Mix)
# ──────────────────────────────────────────────
FROM node:20 AS frontend
WORKDIR /app
# Branding colors are compiled into the CSS at build time (see webpack.mix.js).
# Override via `docker build --build-arg` or GitHub Actions repository variables;
# empty values fall back to the defaults baked into webpack.mix.js.
ARG BOOTSTRAP_COLOR_PRIMARY
ARG BOOTSTRAP_COLOR_SECONDARY
ARG BOOTSTRAP_COLOR_TERTIARY
ARG BOOTSTRAP_COLOR_SUCCESS
ARG BOOTSTRAP_COLOR_WARNING
ARG BOOTSTRAP_COLOR_DANGER
ENV BOOTSTRAP_COLOR_PRIMARY=${BOOTSTRAP_COLOR_PRIMARY} \
    BOOTSTRAP_COLOR_SECONDARY=${BOOTSTRAP_COLOR_SECONDARY} \
    BOOTSTRAP_COLOR_TERTIARY=${BOOTSTRAP_COLOR_TERTIARY} \
    BOOTSTRAP_COLOR_SUCCESS=${BOOTSTRAP_COLOR_SUCCESS} \
    BOOTSTRAP_COLOR_WARNING=${BOOTSTRAP_COLOR_WARNING} \
    BOOTSTRAP_COLOR_DANGER=${BOOTSTRAP_COLOR_DANGER}
COPY package.json package-lock.json ./
RUN npm ci
# Copy the full application context. Laravel Mix only sets its public path to
# "public" when it detects a Laravel app (laravel-mix sees ./artisan); without
# artisan, mix.version() resolves "/public/..." against the filesystem root and
# fails with ENOENT. This stage only contributes public/js|css|mix-manifest.json.
COPY . .
RUN npm run build

# ──────────────────────────────────────────────
# Stage 3 — Runtime
# ──────────────────────────────────────────────
FROM php:8.3-cli

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
    && docker-php-ext-install -j"$(nproc)" \
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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Application code
COPY . .

# Vendored PHP deps + compiled frontend assets
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/js ./public/js
COPY --from=frontend /app/public/css ./public/css
COPY --from=frontend /app/public/mix-manifest.json ./public/mix-manifest.json

# Runtime skeleton. Bind mounts are NOT seeded from the image,
# so the entrypoint recreates these dirs if a host folder is empty.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-interaction --no-scripts \
    && chmod -R 775 storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# ──────────────────────────────────────────────
# Health check — confirms Laravel responds
# ──────────────────────────────────────────────
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:80/ || exit 1

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
