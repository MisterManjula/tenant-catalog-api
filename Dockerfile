FROM php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-libs libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev linux-headers \
 && docker-php-ext-install pdo_pgsql zip pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dev dependencies stay in the image on purpose: tests run inside the container.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
 && chown -R www-data:www-data storage bootstrap/cache
