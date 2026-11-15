# ServiceFlow demo image: Vue SPA + Laravel API in one container, served from one origin.
# Used by Render (render.yaml); works on any Docker host that sets $PORT.

# ---- 1. Build the Vue SPA ----
FROM node:20-alpine AS spa
WORKDIR /spa
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

# ---- 2. Runtime: PHP 8.3 + the API ----
FROM php:8.3-cli-alpine
RUN apk add --no-cache bash \
    && docker-php-ext-install bcmath pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# Dev dependencies are kept on purpose: the demo seeds itself with Faker on every boot.
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY backend/ ./
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && rm -f .env database/database.sqlite

COPY --from=spa /spa/dist/ ./public/
RUN mv public/index.html public/spa.html

COPY docker/render/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=ar \
    APP_FAKER_LOCALE=ar_EG \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    PHP_CLI_SERVER_WORKERS=4

EXPOSE 8080
CMD ["start"]
