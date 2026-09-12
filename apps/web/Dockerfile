########################
# 1) Abhaengigkeiten + Asset-Build
#    PHP und Node muessen in derselben Stage stehen: der Vite-Build ruft ueber
#    das laravel/wayfinder-Plugin "php artisan wayfinder:generate" auf.
########################
FROM php:8.4-cli-alpine AS build

RUN apk add --no-cache nodejs npm
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev

# Vite braucht VITE_*-Variablen beim Bauen selbst, nicht erst zur Laufzeit --
# .env existiert an dieser Stelle noch nicht (siehe .dockerignore).
ARG VITE_APP_NAME="DCM Lab"
ENV VITE_APP_NAME=$VITE_APP_NAME
RUN npm run build

########################
# 2) Laufzeit-Image
########################
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
    su-exec \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql intl mbstring zip bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html

COPY --from=build /app /var/www/html

# Caddy laeuft in einem eigenen Container ohne PHP und braucht dieselben
# statischen Dateien unter demselben absoluten Pfad (sonst stimmt das von
# Caddy gesetzte SCRIPT_FILENAME nicht mit dem, was php-fpm hier sieht,
# ueberein). public/ wird deshalb beiseitegelegt und beim Start in ein
# Shared Volume kopiert, das exakt unter /var/www/html/public gemountet wird.
RUN mv /var/www/html/public /var/www/html/public-image

RUN addgroup -g 1000 dcmlab && adduser -G dcmlab -u 1000 -D dcmlab \
    && chown -R dcmlab:dcmlab /var/www/html/storage /var/www/html/bootstrap/cache \
    && sed -i 's/^user = .*/user = dcmlab/; s/^group = .*/group = dcmlab/' /usr/local/etc/php-fpm.d/www.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Entrypoint startet als root (chown des Shared-Volumes), gibt Rechte dann via
# su-exec an den unprivilegierten Nutzer ab, bevor php-fpm laeuft.
EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
