#!/bin/sh
set -e

cd /app

php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

exec "$@"
