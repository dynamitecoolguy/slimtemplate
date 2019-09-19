FROM mariadb:10.4.8

ENV MYSQL_ROOT_PASSWORD hogehoge
ENV MYSQL_ROOT_HOST 172.28.0.1

ENV MYSQL_DATABASE userdb
ENV MYSQL_USER scott
ENV MYSQL_PASSWORD tiger

COPY init.d/* /docker-entrypoint-initdb.d/

EXPOSE 3306

CMD ["--sql-mode=NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION", \
 "--skip-character-set-client-handshake", \
 "--default-time-zone=+00:00"]