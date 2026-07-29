# Built with the context at the monorepo ROOT, to be able to copy
# packages/bp-common (local dependency via path-repository):
#   docker build -f services/svc-customer-data/Dockerfile -t bp/svc-customer-data .

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
COPY services/svc-customer-data services/svc-customer-data

WORKDIR /app/services/svc-customer-data

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

EXPOSE 8000

# migrate --force is idempotent (Laravel skips what's already applied) --
# safe to run on every container startup, including the Fase 11
# orchestration with `docker compose up`.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000"]
