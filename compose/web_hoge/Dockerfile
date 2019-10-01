FROM php:7.3.9-fpm-alpine3.10

ARG environment
ARG environmentkey=""

RUN apk upgrade --update \
  && apk --no-cache --virtual .build-deps add make g++ gcc re2c autoconf \
  && apk --no-cache add gettext-dev libzip-dev curl-dev \
  && docker-php-ext-install -j$(nproc) gettext mbstring zip opcache ctype json bcmath sockets curl \
  && pecl channel-update pecl.php.net

# PDO(MySQL)
RUN docker-php-ext-install -j$(nproc) pdo_mysql

# PDO(PostgreSQL)
RUN apk --no-cache add postgresql-dev \
  && docker-php-ext-install -j$(nproc) pdo_pgsql

# YAML (2.0.4)extension=yaml.so
RUN apk --no-cache add yaml-dev \
  && pecl install yaml-2.0.4 \
  && docker-php-ext-enable yaml

# APCu (5.1.17)  extension=apcu.so
RUN pecl install apcu-5.1.17 \
  && docker-php-ext-enable apcu

# igbinary (3.0.1) extension=igbinary.so
RUN pecl install igbinary-3.0.1 \
  && docker-php-ext-enable igbinary

# msgpack (2.0.3) extension=msgpack.so
RUN pecl install msgpack-2.0.3 \
  && docker-php-ext-enable msgpack

# redis (5.0.2) extension=redis.so
RUN curl -fsSL https://github.com/phpredis/phpredis/archive/5.0.2.tar.gz -o redis.tar.gz \
  && mkdir -p /usr/src/php/ext/redis \
  && tar xzf redis.tar.gz -C /usr/src/php/ext/redis --strip-components=1 \
  && rm redis.tar.gz \
  && docker-php-ext-configure redis --enable-redis-igbinary \
  && docker-php-ext-install -j$(nproc) redis

# xdebug(2.7.2) extension=xdebug.so
RUN if [ "${environment}" = "local" ]; then \
     pecl install xdebug-2.7.2 \
  && docker-php-ext-enable xdebug \
  && echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.remote_connect_back=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.remote_autostart=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  ; fi

# settings
COPY settings.${environment}.php /var/www/settings.php
RUN sed -i -e"s/{ENVKEY}/${environmentkey}/" /var/www/settings.php

# PHP conf
COPY php.${environment}.ini /usr/local/etc/php/conf.d/php.ini

RUN apk del --purge .build-deps \
  && docker-php-source delete \
  && rm -rf /var/cache/apk/* \
  && rm -rf /tmp/*

RUN deluser www-data \
    && addgroup -g 1000 -S www-data \
    && adduser -u 1000 -D -S -G www-data www-data

