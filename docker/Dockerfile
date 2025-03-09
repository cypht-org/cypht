FROM docker.io/php:8.1-fpm-bookworm

WORKDIR /usr/local/share/cypht

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_CACHE_DIR=/tmp/composer_cache
ENV COMPOSER_HOME=/tmp/composer_home

RUN <<BASH
set -e
apt-get update
apt-get install -y \
    supervisor \
    nginx \
    sqlite3 \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    unzip
apt-get install -y --no-install-recommends \
    ca-certificates
apt-get install -y --no-install-recommends \
    libfreetype6-dev
docker-php-ext-configure gd
docker-php-ext-install \
    session \
    fileinfo \
    dom \
    xml \
    xmlwriter \
    gd \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    mysqli \
    soap
docker-php-ext-configure zip
docker-php-ext-install zip
curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s \
    xdebug redis gnupg memcached
rm -rf /var/lib/apt/lists/*
apt-get clean
ln -sf /dev/stdout /var/log/nginx/access.log
ln -sf /dev/stderr /var/log/nginx/error.log
ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
BASH

RUN printf "[xdebug] \n\
    zend_extension=xdebug.so \n\
    xdebug.mode=debug \n\
    xdebug.client_host=host.docker.internal" > /tmp/xdebug.ini

RUN printf "post_max_size = 60M \n\
    upload_max_filesize = 50M \n\
    open_basedir = /var/lib/hm3/:/usr/local/share/cypht/:/tmp:/usr/local/bin:/tmp/composer_cache:/tmp/composer_home:/etc" > /usr/local/etc/php/conf.d/cypht.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer \
    && composer self-update --2

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY composer.* .

ARG WITH_DEBUG=false

RUN if [ "$WITH_DEBUG" = "true" ]; then \
        mv /tmp/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    fi

RUN composer install

COPY config/ config/
COPY language/ language/
COPY lib/ lib/
COPY modules/ modules/
COPY scripts/ scripts/
COPY database/ database/
COPY third_party/ third_party/
COPY index.php index.php

COPY docker/docker-entrypoint.sh docker/docker-entrypoint.sh
COPY .env.example .env

EXPOSE 80

HEALTHCHECK CMD curl --fail http://localhost || exit 1

ENTRYPOINT ["docker/docker-entrypoint.sh"]
