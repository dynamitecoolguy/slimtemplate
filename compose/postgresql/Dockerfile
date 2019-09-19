FROM postgres:10.10

ENV POSTGRES_PASSWORD hogehoge
ENV POSTGRES_USER root
ENV POSTGRES_DB logdb

COPY init.d/* /docker-entrypoint-initdb.d/

EXPOSE 5432
