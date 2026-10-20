#!/bin/sh
set -eu
export MYSQL_PWD="$MYSQL_PASSWORD"
mysql --user=kafka --database=kafka < /backup/database.sql
