# Development/Review Dockerfile
# Includes dev dependencies (PHPStan, Pint, Scribe) for testing and quality checks.

FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        git \
        unzip \
        libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --optimize-autoloader --no-interaction

COPY . .

RUN cp .env.example.docker .env

RUN composer dump-autoload --optimize

EXPOSE 8000
