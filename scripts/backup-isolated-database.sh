#!/bin/sh
set -eu
case "$1" in /lab/import-runs/import-*) ;; *) exit 1 ;; esac
test ! -e "$1/database-before.sql"
export MYSQL_PWD="$MYSQL_PASSWORD"
mysqldump --user=kafka --single-transaction --no-tablespaces --hex-blob kafka > "$1/database-before.sql"
test -s "$1/database-before.sql"
