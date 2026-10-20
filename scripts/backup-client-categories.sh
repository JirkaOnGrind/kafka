#!/bin/sh
set -eu
case "$1" in /tmp/kafka-import-categories-*) ;; *) exit 1 ;; esac
mkdir "$1"
export MYSQL_PWD="$MYSQL_PASSWORD"
mysqldump --user=kafka --single-transaction --no-tablespaces --hex-blob kafka > "$1/database-before.sql"
test -s "$1/database-before.sql"
