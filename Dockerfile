# LearnVow — Laravel 13 on Northflank Sandbox.
# Single container: nginx + PHP-FPM 8.3 + Supervisor, built from GitHub.
#
# Stages:
#   1. assets — Node 20: npm ci + vite build  → public/build
#   2. app    — php:8.3-fpm: composer install --no-dev, nginx, supervisor
#
# Runtime behaviour lives in docker/entrypoint.sh (waits for MySQL,
# publishes assets, migrates, seeds demo data once, then caches
# config/routes/views). The Dockerfile itself is NOT build-tested yet —
# this machine has no Docker; the first Northflank build is the real test.

# ---------- Stage 1: frontend assets ----------
FROM node:20-bookworm AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

# ---------- Stage 2: PHP runtime ----------
FROM php:8.3-fpm-bookworm

# System deps: nginx + supervisor + build tools for PHP extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        unzip \
        git \
        curl \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        gd \
        intl \
        zip \
        bcmath \
        exif \
        pcntl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer (from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# PHP production settings (opcache + upload limits).
COPY docker/php.ini /usr/local/etc/php/conf.d/learnvow.ini

# Nginx + Supervisor configs (entrypoint substitutes $PORT into nginx).
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh docker/seed-if-empty.php /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# App code (vendor excluded via .dockerignore — installed below).
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader \
        --no-scripts --no-interaction

COPY . .
# Frontend assets built in stage 1 (public/build is gitignored locally).
COPY --from=assets /app/public/build ./public/build

# Writable dirs for the web user (re-applied at runtime, after the
# persistent volume is mounted).
RUN mkdir -p storage/app/private/bookfiles storage/app/public \
        storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
