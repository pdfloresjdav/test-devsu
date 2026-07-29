# Se construye con el contexto en la RAIZ del monorepo, para poder copiar
# packages/bp-common (dependencia local via path-repository):
#   docker build -f services/svc-movimientos/Dockerfile -t bp/svc-movimientos .

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
COPY services/svc-movimientos services/svc-movimientos

WORKDIR /app/services/svc-movimientos

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

EXPOSE 8000

# migrate/movimientos:setup-table/events:setup-bus son idempotentes -- se
# corren en cada arranque para que `docker compose up` deje la tabla
# DynamoDB y el bus de EventBridge listos sin un paso manual aparte.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan movimientos:setup-table && php artisan events:setup-bus && php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000"]
