#!/bin/sh
set -eu
export MYSQL_PWD="$MYSQL_PASSWORD"
if ! mysql --user=kafka_source --database=kafka_source < /source/database.sql 2>/private/import-errors.log; then
    echo 'Source import failed. Detailed output is preserved privately in the source logs directory.' >&2
    exit 1
fi
echo 'Historical database imported into the isolated source.'
