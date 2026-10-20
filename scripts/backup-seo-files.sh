#!/bin/sh
set -eu
cd /var/www/html
# Caller supplies a new private directory on /lab, never a public web path.
case "$1" in /lab/seo-runs/seo-*) ;; *) exit 1 ;; esac
test ! -e "$1/files-before.tar.gz"
for file in .htaccess robots.txt kafka-sitemap-manifest.json modules/kafkaseo override/classes/Link.php themes/kafka/templates/checkout/order-confirmation.tpl; do
    if test -e "$file"; then printf '%s\n' "$file"; fi
done > "$1/files-before.list"
find . -maxdepth 1 -type f -name '*sitemap.xml' -printf '%f\n' >> "$1/files-before.list"
tar -czf "$1/files-before.tar.gz" -T "$1/files-before.list"
