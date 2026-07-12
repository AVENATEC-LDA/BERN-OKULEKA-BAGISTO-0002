#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing bootstrap/cache public/uploads
chown -R www-data:www-data /var/www/html
chmod -R 775 storage bootstrap/cache public

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -n "${APP_KEY:-}" ]; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
fi

sed -i "s|^APP_ENV=.*|APP_ENV=${APP_ENV:-production}|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=${APP_DEBUG:-false}|" .env
sed -i "s|^APP_URL=.*|APP_URL=${APP_URL:-http://localhost}|" .env
sed -i "s|^APP_LOCALE=.*|APP_LOCALE=${APP_LOCALE:-en}|" .env
sed -i "s|^APP_CURRENCY=.*|APP_CURRENCY=${APP_CURRENCY:-USD}|" .env
sed -i "s|^APP_TIMEZONE=.*|APP_TIMEZONE=${APP_TIMEZONE:-UTC}|" .env
sed -i "s|^APP_ADMIN_URL=.*|APP_ADMIN_URL=${APP_ADMIN_URL:-admin}|" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=${DB_CONNECTION:-mysql}|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-mysql}|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT:-3306}|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-bagisto}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-bagisto}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-bagisto}|" .env
sed -i "s|^REDIS_CLIENT=.*|REDIS_CLIENT=${REDIS_CLIENT:-predis}|" .env
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST:-redis}|" .env
sed -i "s|^REDIS_PORT=.*|REDIS_PORT=${REDIS_PORT:-6379}|" .env
sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD:-}|" .env
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=${CACHE_STORE:-redis}|" .env
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}|" .env
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${SESSION_DRIVER:-redis}|" .env
sed -i "s|^FILESYSTEM_DISK=.*|FILESYSTEM_DISK=${FILESYSTEM_DISK:-public}|" .env
sed -i "s|^MAIL_MAILER=.*|MAIL_MAILER=${MAIL_MAILER:-log}|" .env

if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force >/dev/null 2>&1 || true
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan config:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true
php artisan optimize >/dev/null 2>&1 || true

exec "$@"
