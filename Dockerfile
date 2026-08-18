# syntax=docker/dockerfile:1

# ---------- base: PHP + extensões (FrankenPHP = Caddy + PHP num binário) ----------
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    gd \
    opcache \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV SERVER_NAME=:8000

# ---------- dev: código montado por volume, sem build de assets ----------
FROM base AS dev

RUN install-php-extensions xdebug \
 && echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/zz-xdebug.ini

COPY docker/entrypoint.dev.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "php-server", "--root", "/app/public", "-l", ":8000"]

# ---------- assets: build do front ----------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------- prod: imagem final para o Render ----------
FROM base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false \
    OCTANE_SERVER=frankenphp \
    SERVER_NAME=barbearia-oliveira-alves.matheushrm.dev

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

# config:cache fica no entrypoint: no Render as env vars só existem em runtime
RUN composer dump-autoload --optimize --classmap-authoritative \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80 443 443/udp
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
