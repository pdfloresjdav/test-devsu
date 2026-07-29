# Built with the context at the monorepo ROOT, to be able to copy
# packages/bp-common (local dependency via path-repository):
#   docker build -f services/svc-transferencias/Dockerfile -t bp/svc-transferencias .

FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libbrotli-dev pkg-config default-mysql-client curl \
    && docker-php-ext-install pcntl sockets zip pdo_mysql bcmath \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY packages/bp-common packages/bp-common
COPY services/svc-transferencias services/svc-transferencias

WORKDIR /app/services/svc-transferencias

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

EXPOSE 8000

# migrate --force is idempotent -- runs on every startup. Depends on MySQL
# already being healthy (see `depends_on: condition: service_healthy` in
# docker-compose.yml) to avoid failing on a connection not yet accepted.
CMD ["sh", "-c", "php artisan migrate --force && php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000"]
