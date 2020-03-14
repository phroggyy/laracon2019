FROM php:7.4-fpm-alpine as base

RUN apk --update add mysql-client curl git libxml2-dev libzip-dev zip \
    && docker-php-ext-install pdo_mysql zip xml opcache

ENV COMPOSER_HOME ./.composer
COPY --from=composer:1.9.3 /usr/bin/composer /usr/bin/composer

FROM base AS deps

COPY composer.json /var/www/html/composer.json
COPY composer.lock /var/www/html/composer.lock

RUN composer install --no-dev --no-autoloader --no-scripts

FROM base AS prod

COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=deps /var/www/html/vendor /var/www/html/vendor

ADD opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"
RUN composer dump-autoload --optimize
RUN php artisan route:cache
