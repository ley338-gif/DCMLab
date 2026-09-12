#!/bin/sh
set -e

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
    chown dcmlab:dcmlab /var/www/html/.env
fi

if ! grep -q "^APP_KEY=base64" /var/www/html/.env 2>/dev/null; then
    su-exec dcmlab php artisan key:generate --force
fi

su-exec dcmlab php artisan migrate --force

# public/ ist ein Shared Volume (siehe Dockerfile) -- bei jedem Start frisch
# aus dem Image befuellen, sonst ueberlebt ein alter Build (mit inzwischen
# geloeschten/umbenannten Seiten) einen Redeploy und Vite's Manifest passt
# nicht mehr zu den tatsaechlichen Page-Komponenten.
if [ -d /var/www/html/public-image ]; then
    find /var/www/html/public -mindepth 1 -delete
    cp -r /var/www/html/public-image/. /var/www/html/public/
    chown -R dcmlab:dcmlab /var/www/html/public
fi

# php-fpm selbst startet als root und gibt Worker-Prozesse ueber die Pool-
# Konfiguration (user/group = dcmlab) ab -- das ist der uebliche Weg, damit
# der Master-Prozess seinen error_log oeffnen kann.
exec "$@"
