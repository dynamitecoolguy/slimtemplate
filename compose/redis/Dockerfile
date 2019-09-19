FROM redis:5.0.5

#configuration
COPY redis.conf /usr/local/etc/redis/redis.conf

EXPOSE 6379

#command
CMD [ "redis-server", "/usr/local/etc/redis/redis.conf" ]
