#!/bin/sh
set -e

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  echo "→ composer install"
  composer install --no-interaction --prefer-dist
fi

if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

echo "→ aguardando postgres em ${DB_HOST:-postgres}:${DB_PORT:-5432}"
until php -r 'exit(@fsockopen(getenv("DB_HOST")?:"postgres", (int)(getenv("DB_PORT")?:5432)) ? 0 : 1);'; do
  sleep 1
done

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  php artisan migrate --force
fi

exec "$@"
