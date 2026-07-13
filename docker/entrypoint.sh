#!/bin/sh

set -eu

root=/var/www/html

mkdir -p \
    "$root/i/cache" \
    "$root/config/lite-rate" \
    "$root/admin/logs/counts" \
    "$root/admin/logs/login" \
    "$root/admin/logs/lite" \
    "$root/admin/logs/tasks" \
    "$root/admin/logs/upload" \
    "$root/admin/logs/version"

if [ -L "$root/config/lite-rate" ] || [ -L "$root/config/lite.secret.php" ]; then
    echo "Refusing symlinked Lite security state" >&2
    exit 1
fi

chown -R www-data:www-data "$root/i" "$root/config" "$root/admin/logs"

chmod 0755 "$root/i"
find "$root/admin/logs" -type d -exec chmod 0750 {} \;
find "$root/admin/logs" -type f -exec chmod 0640 {} \;
chmod 0750 "$root/config"
chmod 0700 "$root/config/lite-rate"
find "$root/config/lite-rate" -type f -exec chmod 0600 {} \;

if [ -f "$root/config/lite.secret.php" ]; then
    chmod 0600 "$root/config/lite.secret.php"
fi

if [ "$#" -eq 0 ]; then
    set -- apache2-foreground
fi

exec "$@"
