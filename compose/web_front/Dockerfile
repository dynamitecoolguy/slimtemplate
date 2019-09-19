FROM nginx:1.17.3

COPY default.conf /etc/nginx/conf.d/default.conf

RUN deluser www-data \
    && addgroup --system --gid 1000 www-data \
    && adduser --system --home /var/www --no-create-home --uid 1000 --gid 1000 \
       --disabled-password --disabled-login --shell /usr/sbin/nologin www-data

EXPOSE 3128
EXPOSE 8443

