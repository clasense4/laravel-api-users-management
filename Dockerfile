# Development/Review Dockerfile
# Includes dev dependencies (PHPStan, Pint, Scribe) for testing and quality checks.

FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        git \
        unzip \
        libpq-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --optimize-autoloader --no-interaction

COPY . .

RUN composer dump-autoload --optimize

EXPOSE 8000
