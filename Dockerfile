FROM php:7.4-fpm-alpine as base

RUN apk --update add mysql-client curl git libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql zip xml opcache

ADD opcache.ini "ADD docker/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini""
ENV COMPOSER_HOME ./.composer
COPY --from=composer:1.9.3 /usr/bin/composer /usr/bin/composer

FROM base AS deps

RUN apk add zip

COPY composer.json /var/www/html/composer.json
COPY composer.lock /var/www/html/composer.lock

RUN composer install --no-dev --no-autoloader --no-scripts

FROM base AS prod

COPY . /var/www/html
COPY --from=deps /var/www/html/vendor /var/www/html/vendor

RUN composer dump-autoload
RUN php artisan route:cache
