# Built with the context at the monorepo ROOT, to be able to copy
# packages/bp-common (local dependency via path-repository):
#   docker build -f services/svc-audit/Dockerfile -t bp/svc-audit .
#
# Unlike the HTTP services (BFFs, Customer Data, Movements, Transfers),
# this is a pure worker: it doesn't serve HTTP traffic, so it doesn't need
# Octane/Swoole -- the container's main process is the SQS consume command
# (equivalent to a "Laravel Horizon Worker" in the container diagram).

FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev pkg-config \
    && docker-php-ext-install pcntl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY packages/bp-common packages/bp-common
COPY services/svc-audit services/svc-audit

WORKDIR /app/services/svc-audit

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

# migrate/audit:setup-infrastructure are idempotent -- run on every startup
# so `docker compose up` leaves the SQS queue+DLQ, the EventBridge rule,
# the DynamoDB table and the S3 bucket ready without any manual step.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan audit:setup-infrastructure && php artisan audit:consume"]
