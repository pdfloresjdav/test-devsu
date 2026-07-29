# Built with the context at the monorepo ROOT, to be able to copy
# packages/bp-common (local dependency via path-repository):
#   docker build -f services/svc-notificaciones/Dockerfile -t bp/svc-notificaciones .
#
# Pure worker, same as svc-auditoria: doesn't serve HTTP, doesn't need
# Octane/Swoole. The main process is the SQS consume command.

FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev pkg-config \
    && docker-php-ext-install pcntl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY packages/bp-common packages/bp-common
COPY services/svc-notificaciones services/svc-notificaciones

WORKDIR /app/services/svc-notificaciones

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

# migrate/notifications:setup-infrastructure are idempotent -- run on every
# startup to leave its own SQS queue+DLQ, EventBridge rule and DynamoDB
# table ready without any extra manual step.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan notifications:setup-infrastructure && php artisan notifications:consume"]
