FROM php:7.4.33-fpm-alpine

# ENV CYPHT_DEST "/usr/local/share/cypht"

# WORKDIR "/var/www"

# TODO: change this to /app for simplification?
WORKDIR "/usr/local/share/cypht"

RUN set -e \
    && apk add --no-cache \
    supervisor nginx composer sqlite \
    # GD
    freetype libpng libjpeg-turbo \
    php-session php-fileinfo php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer \
    && apk add --no-cache --virtual .build-deps \
    ca-certificates \
    # For GD (2fa module)
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo pdo_mysql \
    && apk del .build-deps \
    && echo "post_max_size = 60M" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/php.ini \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log 
    # TODO: can we pipe php-fpm messages to stdout here?

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY composer.* .

# TODO: probably dont want to run update here since it modifies composer.lock
RUN composer update && composer self-update --2 && composer install
# RUN composer install

COPY . .
COPY .env.example .env

EXPOSE 80 443

ENTRYPOINT ["docker/docker-entrypoint.sh"]
