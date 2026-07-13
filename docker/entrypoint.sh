#!/bin/sh

set -eu

root=/var/www/html
dynamic_conf=/etc/apache2/conf-enabled/easyimage-lite-path.conf
app_path=$(printf '%s' "${LITE_APP_PATH-}" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

rm -f "$dynamic_conf"

case "$app_path" in
    ''|'/lite')
        ;;
    '/')
        {
            printf '%s\n' 'Alias "/i/" "/var/www/html/i/"'
            printf '%s\n' 'Alias "/" "/var/www/html/lite/"'
            printf '%s\n' '<Directory "/var/www/html/lite">' '    Require all granted' '</Directory>'
        } > "$dynamic_conf"
        ;;
    /*)
        case "$app_path" in
            *//*|*/|*[!A-Za-z0-9_/-]*)
                echo "Invalid LITE_APP_PATH" >&2
                exit 1
                ;;
        esac
        {
            printf 'Alias "%s/" "/var/www/html/lite/"\n' "$app_path"
            printf '%s\n' '<Directory "/var/www/html/lite">' '    Require all granted' '</Directory>'
        } > "$dynamic_conf"
        ;;
    *)
        echo "Invalid LITE_APP_PATH" >&2
        exit 1
        ;;
esac

mkdir -p \
    "$root/i/cache" \
    "$root/config/lite-rate" \
    "$root/admin/logs/counts" \
    "$root/admin/logs/login" \
    "$root/admin/logs/lite" \
    "$root/admin/logs/tasks" \
    "$root/admin/logs/upload" \
    "$root/admin/logs/version"

for path in \
    "$root/config/lite-rate" \
    "$root/config/lite.secret.php" \
    "$root/config/lite.local.php" \
    "$root/config/lite.setup.php" \
    "$root/config/lite.tokens.php" \
    "$root/config/lite.tokens.lock"
do
    if [ -L "$path" ]; then
        echo "Refusing symlinked Lite security state: $path" >&2
        exit 1
    fi
done

for path in "$root/config/lite.tokens.php" "$root/config/lite.tokens.lock"
do
    if [ -e "$path" ] && [ ! -f "$path" ]; then
        echo "Refusing invalid Lite token state: $path" >&2
        exit 1
    fi
done

if [ ! -e "$root/config/lite.tokens.php" ]; then
    (umask 077; printf '%s\n' '<?php exit; ?>' '{"version":1,"tokens":[]}' > "$root/config/lite.tokens.php")
fi
if [ ! -e "$root/config/lite.tokens.lock" ]; then
    (umask 077; : > "$root/config/lite.tokens.lock")
fi

chown -R www-data:www-data "$root/i" "$root/admin/logs"
chown www-data:www-data "$root/config" "$root/config/lite-rate"

chmod 0755 "$root/i"
find "$root/admin/logs" -type d -exec chmod 0750 {} \;
find "$root/admin/logs" -type f -exec chmod 0640 {} \;
chmod 0750 "$root/config"
chmod 0700 "$root/config/lite-rate"
find "$root/config/lite-rate" -type f -exec chmod 0600 {} \;

for file in \
    "$root/config/lite.secret.php" \
    "$root/config/lite.local.php" \
    "$root/config/lite.setup.php" \
    "$root/config/lite.tokens.php" \
    "$root/config/lite.tokens.lock"
do
    if [ -f "$file" ]; then
        chown www-data:www-data "$file"
        chmod 0600 "$file"
    fi
done

if [ "$#" -eq 0 ]; then
    set -- apache2-foreground
fi

exec "$@"
