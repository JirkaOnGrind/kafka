#!/bin/sh
set -eu
if [ -e /var/www/html/index.php ]; then
    echo 'Refusing to copy source files over an existing shop volume.' >&2
    exit 1
fi
tar -xzf /original/source-files.tar.gz -C /var/www/html --anchored --exclude=cache --exclude=log --exclude='.ht*' --exclude='*_sitemap.xml'
# Replace production DB credentials with the already-prepared private settings
# before any historical PHP is executed. Retain original password hashing keys.
cp /source/shop/config/settings.inc.php /var/www/html/config/settings.inc.php
mkdir -p /var/www/html/cache/smarty/cache /var/www/html/cache/smarty/compile /var/www/html/cache/cachefs /var/www/html/log
printf 'User-agent: *\nDisallow: /\n' > /var/www/html/robots.txt
echo 'Prepared source files copied into a private Docker volume.'
