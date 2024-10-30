FROM php:8.1-fpm-alpine3.18

WORKDIR "/usr/local/share/cypht"

RUN set -e; \
    apk add --no-cache supervisor nginx composer sqlite freetype libpng libjpeg-turbo \
    php-session php-fileinfo php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer

RUN apk add --no-cache --virtual .build-deps \
    ca-certificates libpng-dev libjpeg-turbo-dev freetype-dev

RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo pdo_mysql mysqli

RUN curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s \
    xdebug redis gnupg memcached

RUN apk del .build-deps

RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Copier les fichiers nécessaires pour Composer
COPY composer.json composer.lock ./

# Mettre à jour Composer et installer les dépendances
RUN composer self-update --2 \
    && composer install --no-dev --prefer-dist --optimize-autoloader

COPY config/ config/
COPY language/ language/
COPY lib/ lib/
COPY modules/ modules/
COPY scripts/ scripts/
COPY third_party/ third_party/
COPY index.php index.php

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/docker-entrypoint.sh docker/docker-entrypoint.sh
COPY .env.example .env

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl --fail http://localhost || exit 1

ENTRYPOINT ["docker/docker-entrypoint.sh"]