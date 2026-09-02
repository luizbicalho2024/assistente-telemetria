FROM php:8.5.10-apache-bookworm

ARG MONGODB_EXTENSION_VERSION=2.4.1
ARG INSTALL_DEV=false

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libssl-dev libcurl4-openssl-dev pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl intl zip gd mbstring dom simplexml xml xmlreader xmlwriter \
    && pecl install "mongodb-${MONGODB_EXTENSION_VERSION}" \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite headers expires \
    && printf '%s\n' \
        'expose_php=Off' \
        'display_errors=Off' \
        'log_errors=On' \
        'memory_limit=512M' \
        'max_execution_time=120' \
        'max_input_time=120' \
        'upload_max_filesize=16M' \
        'post_max_size=128M' \
        'max_file_uploads=24' \
        'session.cookie_httponly=1' \
        > /usr/local/etc/php/conf.d/99-security.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . .

RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chmod -R ug+rwx storage bootstrap/cache \
    && if [ "${INSTALL_DEV}" = "true" ]; then \
         composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader; \
       else \
         composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative; \
       fi \
    && composer audit --locked \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x /var/www/html/docker/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=5 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
