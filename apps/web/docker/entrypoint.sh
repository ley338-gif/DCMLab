#!/bin/sh
set -e

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
    chown dcmlab:dcmlab /var/www/html/.env
fi

# APP_KEY kommt seit ADR 0087 zentral aus der Compose-Umgebung (services.app/
# services.scheduler.environment: APP_KEY, aus der Root-.env) -- ein bereits
# gesetzter Prozess-Umgebungswert hat bei Laravel/phpdotenv immer Vorrang vor
# der .env-Datei, key:generate darf ihn also nicht ueberschreiben. Ohne einen
# extern gesetzten Wert (z. B. bei einem Start ausserhalb von Compose)
# generiert und persistiert diese Zeile weiterhin selbst einen, wie zuvor --
# das war vorher der einzige Weg und bleibt der Fallback.
if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64" /var/www/html/.env 2>/dev/null; then
    su-exec dcmlab php artisan key:generate --force
fi

# --isolated: zwei Container auf demselben Image (app + scheduler, ADR 0087)
# koennen gleichzeitig starten und versuchen sonst, dieselbe Migration
# parallel auszufuehren -- die Sperre laeuft ueber CACHE_STORE (redis/Valkey
# in jeder Compose-Umgebung), macht einen Doppel-Lauf ungefaehrlich.
su-exec dcmlab php artisan migrate --force --isolated

# public/ ist ein Shared Volume (siehe Dockerfile) -- bei jedem Start frisch
# aus dem Image befuellen, sonst ueberlebt ein alter Build (mit inzwischen
# geloeschten/umbenannten Seiten) einen Redeploy und Vite's Manifest passt
# nicht mehr zu den tatsaechlichen Page-Komponenten. Nur fuer den Web-
# Container (Kommando "php-fpm") -- ein zweiter, gleichzeitig startender
# Container (z. B. der scheduler-Service, der stattdessen
# "php artisan schedule:work" ausfuehrt) wuerde sich sonst mit diesem
# Container einen Loesch-/Kopier-Race auf demselben Volume liefern (ADR 0087).
if [ "$1" = "php-fpm" ] && [ -d /var/www/html/public-image ]; then
    find /var/www/html/public -mindepth 1 -delete
    cp -r /var/www/html/public-image/. /var/www/html/public/
    chown -R dcmlab:dcmlab /var/www/html/public
fi

# php-fpm selbst startet als root und gibt Worker-Prozesse ueber die Pool-
# Konfiguration (user/group = dcmlab) ab -- das ist der uebliche Weg, damit
# der Master-Prozess seinen error_log oeffnen kann.
exec "$@"
