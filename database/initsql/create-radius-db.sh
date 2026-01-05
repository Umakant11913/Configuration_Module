#!/usr/bin/env bash

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS radius;
    GRANT ALL PRIVILEGES ON radius.* TO '$MYSQL_USER'@'%';
EOSQL
