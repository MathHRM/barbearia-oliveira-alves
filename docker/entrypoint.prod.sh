#!/bin/sh
set -e

cd /app

# The production storage volume is empty on its first mount. Laravel's cache
# commands expect these runtime directories to exist.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

exec "$@"
