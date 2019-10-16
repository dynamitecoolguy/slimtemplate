FROM alpine:3.10.2

ARG environment

RUN apk upgrade --update \
  && apk --no-cache --virtual .build-deps add make g++ gcc re2c autoconf curl \
  && apk --no-cache add gettext-dev libzip-dev curl-dev

RUN apk --no-cache add supervisor tzdata nginx php7 php7-fpm \
     php7-gettext php7-mbstring php7-zip php7-ctype php7-json php7-bcmath php7-sockets php7-curl php7-simplexml \
     php7-pecl-apcu php7-pecl-igbinary php7-pecl-msgpack

# PDO(MySQL)
RUN apk --no-cache add mariadb-dev php7-pdo_mysql

# PDO(PostgreSQL)
RUN apk --no-cache add postgresql-dev php7-pdo_pgsql

# YAML (2.0.4)
RUN apk --no-cache add yaml-dev php7-pecl-yaml

# install phpize
RUN apk --no-cache add php7-dev

# redis (5.0.2) extension=redis.so
RUN curl -fsSL https://github.com/phpredis/phpredis/archive/5.0.2.tar.gz -o redis.tar.gz \
  && mkdir -p /usr/src/php/ext/redis \
  && tar xzf redis.tar.gz -C /usr/src/php/ext/redis --strip-components=1 \
  && rm redis.tar.gz \
  && cd /usr/src/php/ext/redis \
  && phpize \
  && ./configure --enable-redis-igbinary \
  && make \
  && make install \
  && echo "extension=redis.so" > /etc/php7/conf.d/80_redis.ini \
  && make distclean

# remove phpize
RUN apk del php7-dev

# xdebug(2.7.2) extension=xdebug.so
RUN if [ "${environment}" = "local" ]; then \
     apk --no-cache add php7-pecl-xdebug \
  && echo "zend_extension=xdebug.so" > /etc/php7/conf.d/xdebug.ini \
  && echo "[XDebug]" >> /etc/php7/conf.d/xdebug.ini \
  && echo "xdebug.remote_enable=1" >> /etc/php7/conf.d/xdebug.ini \
  && echo "xdebug.remote_connect_back=0" >> /etc/php7/conf.d/xdebug.ini \
  && echo "xdebug.remote_autostart=0" >> /etc/php7/conf.d/xdebug.ini \
  ; fi

# TZ
RUN cp /usr/share/zoneinfo/UTC /etc/localtime \
  && echo "UTC" > /etc/timezone

# supervisord
RUN mkdir -p /etc/supervisor.d
COPY supervisord.conf /etc/supervisord.conf

# settings
COPY settings-${environment}.yml /var/www/settings.yml

# PHP
COPY php.ini /php.ini
COPY php-fpm.conf /php-fpm.conf
COPY php-fpm-supervisor.conf /php-fpm-supervisor.conf
RUN cp /php-fpm.conf /etc/php7/php-fpm.d/z-php.conf \
  && cp /php.ini /etc/php7/conf.d/config.ini \
  && cp /php-fpm-supervisor.conf /etc/supervisor.d/php-fpm.conf

# Nginx
COPY nginx.conf /nginx.conf
COPY nginx-server.conf /nginx-server.conf
COPY nginx-supervisor.conf /nginx-supervisor.conf
RUN cp /nginx.conf /etc/nginx/nginx.conf \
  && cp /nginx-server.conf /etc/nginx/conf.d/default.conf \
  && cp /nginx-supervisor.conf /etc/supervisor.d/nginx.conf

RUN apk del --purge .build-deps \
  && rm -rf /var/cache/apk/* \
  && rm -rf /tmp/*

RUN deluser nginx \
    && delgroup www-data \
    && addgroup -g 1000 -S www-data \
    && adduser -u 1000 -D -S -G www-data www-data \
    && adduser -u 1001 -D -S -G www-data nginx

EXPOSE 3128

ENTRYPOINT ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]