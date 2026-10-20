#!/bin/sh
set -eu
# Legacy PHP mmap can read stale sizes on a Windows file bind after edits.
# Copy the helper into Linux storage before parsing it.
cp /isolation/configure.php /tmp/kafka-configure-source.php
php /tmp/kafka-configure-source.php
