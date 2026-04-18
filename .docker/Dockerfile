ARG COMPOSER_VERSION=2.9.1
ARG PHP_IMAGE_VERSION=8.4-apache-bookworm

FROM composer:$COMPOSER_VERSION AS composer-src
FROM php:$PHP_IMAGE_VERSION AS base

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="0" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="50000" \
    PHP_OPCACHE_MEMORY_CONSUMPTION="192" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10"

RUN apt-get update \
    && apt-get install -y \
        zlib1g-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        supervisor \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
        bcmath \
        mbstring \
        gd \
        pdo \
        pdo_mysql \
        zip \
        pcntl \
        intl \
        opcache

RUN pecl install redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

COPY --from=composer-src /usr/bin/composer /usr/bin/composer

RUN mkdir /run/supervisor \
    && chown -R www-data:www-data /run/supervisor \
    && chown -R www-data:www-data /var/log/supervisor

COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN echo 'memory_limit = 2G' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini \
    && echo 'max_execution_time = 500' >> /usr/local/etc/php/conf.d/docker-php-maxexectime.ini \
    && echo 'default_socket_timeout = -1' >> /usr/local/etc/php/conf.d/docker-php-sockettimeout.ini \
    && sed -i "s/Listen 80/Listen 8080/" /etc/apache2/ports.conf

RUN a2enmod rewrite

WORKDIR /var/www

RUN chown -R www-data:www-data .

COPY --chown=www-data:www-data .docker/supervisor/supervisord.conf /etc/supervisor/
COPY --chown=www-data:www-data .docker/supervisor/conf.d/* /etc/supervisor/conf.d/
COPY --chown=www-data:www-data .docker/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --chown=www-data:www-data composer* ./
COPY --chown=www-data:www-data database ./database

RUN mkdir /app
COPY --chown=www-data:www-data .docker/entrypoint.sh /app/
RUN chmod +x /app/entrypoint.sh

ENTRYPOINT ["/app/entrypoint.sh"]

RUN composer install \
    --no-dev \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

EXPOSE 8080


FROM base AS development

ARG WWWGROUP
ARG WWWUSER

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN groupmod --gid $WWWGROUP www-data
RUN usermod --uid $WWWUSER www-data

RUN chown -R $WWWUSER:$WWWGROUP .

COPY --chown=$WWWUSER:$WWWGROUP . .

USER www-data

RUN mkdir -p storage/framework/cache


FROM base AS ci

ARG WWWGROUP
ARG WWWUSER

RUN pecl install pcov \
    && docker-php-ext-enable pcov

RUN groupmod --gid $WWWGROUP www-data
RUN usermod --uid $WWWUSER www-data

RUN chown -R $WWWUSER:$WWWGROUP .

USER www-data

RUN composer install \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

COPY --chown=www-data:www-data .env.example .env
COPY --chown=$WWWUSER:$WWWGROUP . .

RUN mkdir -p storage/framework/cache

RUN composer run-script post-autoload-dump
RUN php artisan key:generate


FROM base AS production

COPY --chown=www-data:www-data .env.example .env
COPY --chown=www-data:www-data . .

USER www-data

RUN mkdir -p storage/framework/cache storage/framework/views
RUN chmod 775 -R /var/www/storage/
