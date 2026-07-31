FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache bash git curl zip unzip \
    && install-php-extensions pdo_pgsql pgsql zip gd bcmath intl opcache pcntl redis dom mbstring

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Headless Chrome for invoice PDF rendering (see config/browsershot.php) —
# dompdf has no OpenType shaping engine and can't render complex scripts
# like Khmer correctly, so invoices render through real Chrome via
# Puppeteer instead. Chromium/Node come from apk rather than letting
# Puppeteer download its own build, which ships a glibc Chromium that
# won't run on Alpine's musl libc.
RUN apk add --no-cache chromium nodejs npm nss freetype harfbuzz ca-certificates ttf-freefont

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

# Same glibc-vs-musl reason as the apk chromium above — skip Puppeteer's
# own Chromium download and point it at the system one instead (see
# BROWSERSHOT_CHROME_PATH in config/browsershot.php).
RUN PUPPETEER_SKIP_DOWNLOAD=true npm install puppeteer --prefix /var/www/html

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/node_modules

EXPOSE 9000
CMD ["php-fpm"]
