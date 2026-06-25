FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    # Сборочные зависимости — до компиляции расширений; linux-headers нужен ext-sockets (sock_diag.h),
    # openssl-dev — для ext-mongodb (TLS-соединение с брокером Mongo).
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers openssl-dev \
    && docker-php-ext-install \
        intl \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis mongodb \
    && docker-php-ext-enable redis mongodb \
    && apk del .build-deps

# Дефолтный memory_limit (128M) мал для статанализа/artisan — поднимаем для CLI и FPM.
RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/zz-docsign.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
