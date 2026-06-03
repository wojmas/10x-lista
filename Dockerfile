# syntax=docker/dockerfile:1

# --- etap 1: build assetów frontu (Vite + Tailwind) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json vite.config.js ./
RUN npm install
COPY resources ./resources
RUN npm run build

# --- etap 2: runtime PHP (php-fpm + nginx + supervisor) ---
# php 8.4: composer.lock ciągnie Symfony 8 (wymaga php >=8.4); spełnia wymóg tech-stack "PHP 8.3+".
FROM php:8.4-fpm

# Rozszerzenia PHP pod Laravela + serwer/proces-manager
RUN apt-get update && apt-get install -y \
        git curl libpng-dev libonig-dev libxml2-dev libpq-dev zip unzip \
        nginx supervisor gettext-base \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Najpierw zależności (warstwa cache'owana, gdy composer.* się nie zmienia)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Kod aplikacji + zbudowane assety z etapu 1
COPY . /var/www
COPY --from=assets /app/public/build /var/www/public/build

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

# Konfiguracja serwera/procesów
COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Informacyjnie — Render i tak wstrzykuje $PORT (domyślnie 10000)
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
