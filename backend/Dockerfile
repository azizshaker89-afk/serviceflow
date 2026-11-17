# ServiceFlow API image for the Vercel "backend" service (see ../vercel.json).
# The Vue SPA is a separate static service; Vercel routes /api, /sanctum and /up here.
# The root ../Dockerfile is the all-in-one image for Hugging Face / Render / any Docker host.
FROM php:8.3-cli-alpine
RUN apk add --no-cache bash \
    && docker-php-ext-install bcmath pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
# Dev dependencies are kept on purpose: the demo seeds itself with Faker.
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . ./
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && rm -f .env database/database.sqlite \
    && chmod +x vercel-start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=ar \
    APP_FAKER_LOCALE=ar_EG \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    SESSION_DRIVER=cookie \
    SESSION_SECURE_COOKIE=true \
    SANCTUM_TRUST_REQUEST_HOST=true \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    PHP_CLI_SERVER_WORKERS=4

# Seed once at build time so a cold start only copies this file (the start script reseeds if stale).
RUN touch database/snapshot.sqlite \
    && APP_KEY=base64:$(head -c 32 /dev/urandom | base64) DB_DATABASE=/app/database/snapshot.sqlite \
       php artisan migrate --force --seed --no-interaction

# Vercel routes traffic to $PORT (default 80).
CMD ["/app/vercel-start.sh"]
