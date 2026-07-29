# Built with the context at the monorepo ROOT, to be able to copy
# packages/bp-common (local dependency via path-repository):
#   docker build -f services/svc-movements/Dockerfile -t bp/svc-movements .

FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libbrotli-dev pkg-config curl \
    && docker-php-ext-install pcntl sockets zip \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY packages/bp-common packages/bp-common
COPY services/svc-movements services/svc-movements

WORKDIR /app/services/svc-movements

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

EXPOSE 8000

# migrate/movements:setup-table/events:setup-bus are idempotent -- run on
# every startup so `docker compose up` leaves the DynamoDB table and the
# EventBridge bus ready without any extra manual step.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan movements:setup-table && php artisan events:setup-bus && php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000"]
