FROM php:7.4.33-fpm-alpine

WORKDIR "/usr/local/share/cypht"

RUN set -e \
    && apk add --no-cache \
    supervisor nginx composer sqlite \
    freetype libpng libjpeg-turbo \
    php-session php-fileinfo php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer \
    && apk add --no-cache --virtual .build-deps \
    ca-certificates \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo pdo_mysql \
    && composer self-update --2 \
    && apk del .build-deps \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && ln -s /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

COPY <<EOF /usr/local/etc/php/conf.d/cypht.ini
post_max_size = 60M
upload_max_filesize = 50M
EOF

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY composer.* .

# TODO: figure out missing extensions https://github.com/cypht-org/cypht/issues/1009
# RUN  pecl install redis gnupg memcached

RUN composer install

COPY . .
COPY .env.example .env

EXPOSE 80

HEALTHCHECK CMD curl --fail http://localhost || exit 1

ENTRYPOINT ["docker/docker-entrypoint.sh"]
