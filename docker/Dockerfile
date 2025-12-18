FROM php:8.1-fpm-bookworm

WORKDIR /usr/local/share/cypht

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_CACHE_DIR=/tmp/composer_cache \
    COMPOSER_HOME=/tmp/composer_home

# Install system packages and PHP extensions
RUN set -eux \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        supervisor \
        nginx \
        sqlite3 \
        libfreetype6-dev \
        libpng-dev \
        libjpeg-dev \
        libxml2-dev \
        libzip-dev \
        libpq-dev \
        unzip \
        ca-certificates \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
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
        pgsql \
        soap \
        zip \
    && curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s \
        xdebug redis gnupg memcached ldap \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/php /var/log/supervisord \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && ln -sf /dev/stdout /var/log/php/fpm.log \
    && ln -sf /dev/stderr /var/log/php/fpm-error.log \
    && ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# PHP Config
RUN printf "[xdebug] \n\
zend_extension=xdebug.so \n\
xdebug.mode=debug \n\
xdebug.client_host=host.docker.internal" > /usr/local/etc/php/conf.d/xdebug.ini

RUN printf "post_max_size = 60M \n\
upload_max_filesize = 50M \n\
open_basedir = /var/lib/hm3/:/usr/local/share/cypht/:/tmp:/usr/local/bin:/tmp/composer_cache:/tmp/composer_home:/etc" > /usr/local/etc/php/conf.d/cypht.ini

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer \
    && composer self-update --2

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Application and config files
COPY composer.* ./
RUN composer install

COPY assets/ assets/
COPY config/ config/
COPY language/ language/
COPY lib/ lib/
COPY modules/ modules/
COPY scripts/ scripts/
COPY database/ database/
COPY third_party/ third_party/
COPY index.php index.php

# Entrypoint
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
COPY .env.example .env

# Healthcheck
HEALTHCHECK CMD curl --fail http://localhost || exit 1

# Expose port and start
EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
