FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash git curl libzip-dev zip unzip \
        postgresql-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev oniguruma-dev linux-headers \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql zip gd bcmath intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
