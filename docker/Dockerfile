FROM php:7.4.33-fpm-alpine

WORKDIR "/usr/local/share/cypht"

RUN set -e \
    && apk add --no-cache \
    supervisor nginx composer sqlite freetype libpng libjpeg-turbo \
    php-session php-fileinfo php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer \
    && apk add --no-cache --virtual .build-deps \
    ca-certificates \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo pdo_mysql \
    && curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s \
    xdebug redis gnupg memcached \
    && composer self-update --2 \
    && apk del .build-deps \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

COPY <<EOF /tmp/xdebug.ini
[xdebug]
zend_extension=xdebug.so

xdebug.mode=debug
xdebug.client_host=host.docker.internal
EOF

COPY <<EOF /usr/local/etc/php/conf.d/cypht.ini
post_max_size = 60M
upload_max_filesize = 50M
# the following is needed for sqlite access
open_basedir = /var/lib/hm3/:/usr/local/share/cypht/:/tmp
EOF

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY composer.* .

ARG WITH_DEBUG=false

RUN [ "$WITH_DEBUG" = "true" ] && mv /tmp/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini || true

RUN composer install

COPY config/ config/
COPY language/ language/
COPY lib/ lib/
COPY modules/ modules/
COPY scripts/ scripts/
COPY third_party/ third_party/
COPY index.php index.php

COPY docker/docker-entrypoint.sh docker/docker-entrypoint.sh
COPY .env.example .env

EXPOSE 80

HEALTHCHECK CMD curl --fail http://localhost || exit 1

ENTRYPOINT ["docker/docker-entrypoint.sh"]
