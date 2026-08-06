FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan event:cache

EXPOSE 8000
