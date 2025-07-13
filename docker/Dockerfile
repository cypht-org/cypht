FROM php:8.1-fpm-alpine

WORKDIR /usr/local/share/cypht

# Install system packages and PHP extensions
RUN set -e \
    && apk add --no-cache \
        supervisor nginx curl composer sqlite \
        freetype libpng libjpeg-turbo \
        php-session php-fileinfo php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer \
    && apk add --no-cache --virtual .build-deps \
        ca-certificates libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo pdo_mysql mysqli \
    && curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions | sh -s \
        xdebug redis gnupg memcached \
    && composer self-update --2 \
    && apk del .build-deps \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && mkdir -p /var/log/php /var/log/supervisord \
    && ln -sf /dev/stdout /var/log/php/fpm.log \
    && ln -sf /dev/stderr /var/log/php/fpm-error.log \
    && ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Configuration files
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/cypht.ini /usr/local/etc/php/conf.d/cypht.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Composer
COPY composer.* ./
RUN composer install

# Application source
COPY config/ config/
COPY language/ language/
COPY lib/ lib/
COPY modules/ modules/
COPY scripts/ scripts/
COPY third_party/ third_party/
COPY index.php index.php

# Entrypoint
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Healthcheck
HEALTHCHECK CMD curl --fail http://localhost || exit 1

# Expose port and start
EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
