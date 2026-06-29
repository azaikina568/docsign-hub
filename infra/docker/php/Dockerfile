FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Расширения ставим прекомпилированными (mlocati): бинарники тянутся готовыми и установщик сам
# подтягивает нужные build/runtime-зависимости и чистит их — это в разы быстрее ручной компиляции
# через pecl/docker-php-ext-install (раньше холодная сборка занимала ~7 минут).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/bin/

RUN apk add --no-cache bash git \
    && install-php-extensions \
        intl \
        mongodb \
        pcntl \
        pdo_pgsql \
        redis \
        sockets \
        zip

# Дефолтный memory_limit (128M) мал для статанализа/artisan, поднимаем для CLI и FPM.
RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/zz-docsign.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
