# FROM php:fpm-alpine
FROM php:7.4.33-fpm-alpine

WORKDIR "/usr/local/share/cypht"

COPY .github/docker/nginx.conf /etc/nginx/nginx.conf
COPY .github/docker/supervisord.conf /etc/supervisord.conf
COPY .github/docker/docker-entrypoint.sh /usr/local/bin/
COPY .github/docker/cypht_setup_database.php /tmp/

COPY config/ config/
COPY language/ language/
COPY lib/ lib/
COPY modules/ modules/
COPY scripts/ scripts/
COPY third_party/ third_party/
COPY index.php index.php
COPY composer.json composer.json
COPY composer.lock composer.lock
COPY .env.example .env

RUN set -e \
    rm -rf /var/www \
    && apk add --no-cache \
    bash \
    nginx \
    composer \
    supervisor \
    sqlite \
    # GD
    freetype libpng libjpeg-turbo \
    php-session php-fileinfo  php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer \
    && apk add --no-cache --virtual .build-deps \
    wget \
    # For GD (2fa module)
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo_mysql \
    && apk del .build-deps \
    && find . -type d -print | xargs chmod 755 \
    && find . -type f -print | xargs chmod 644 \
    && chown -R root:root ./ \
    && composer update \
    && composer install \
    && echo "post_max_size = 60M" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/php.ini

RUN set -e \
    ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && chmod 700 /tmp/cypht_setup_database.php \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR "/var/www"

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]