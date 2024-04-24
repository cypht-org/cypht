FROM php:fpm-alpine

ENV CYPHT_DEST "/usr/local/share/cypht"

WORKDIR "/var/www"

RUN set -e \
    apk update && apk upgrade \
    && apk add --no-cache \
    bash \
    nginx \
    composer \
    supervisor \
    # GD
    freetype libpng libjpeg-turbo \
    php-session php-fileinfo  php-dom php-xml libxml2-dev php-xmlwriter php-tokenizer \
    && apk add --no-cache --virtual .build-deps \
    wget \
    # For GD (2fa module)
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd pdo_mysql \
    # Install
    # && wget https://github.com/cypht-org/cypht/archive/master.zip \
    && wget https://github.com/wangxiaoeryah/cypht/archive/dev.zip \
    # && unzip master.zip \
    # && rm master.zip \
    && unzip dev.zip \
    && rm dev.zip \
    && mkdir -p ${CYPHT_DEST} \
    # && mv cypht-master/* ${CYPHT_DEST} \
    && mv cypht-dev/* ${CYPHT_DEST} \
    && find ${CYPHT_DEST} -type d -print | xargs chmod 755 \
    && find ${CYPHT_DEST} -type f -print | xargs chmod 644 \
    && chown -R root:root ${CYPHT_DEST} \
    && apk del .build-deps \
    && rm -rm ./localhost \
    && cd ${CYPHT_DEST} \
    && cp .env.example .env \
    && composer update \
    && composer install \
    && echo "post_max_size = 60M" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/php.ini    

RUN set -ex \
    && cd ${CYPHT_DEST} \
    && cp .github/docker/nginx.conf /etc/nginx/nginx.conf \
    && cp .github/docker/supervisord.conf /etc/supervisord.conf \
    && cp .github/docker/docker-entrypoint.sh /usr/local/bin/ \
    && cp .github/docker/cypht_setup_database.php /tmp/ \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && chmod 700 /tmp/cypht_setup_database.php \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["docker-entrypoint.sh"]