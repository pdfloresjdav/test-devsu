# Se construye con el contexto en la RAIZ del monorepo, para poder copiar
# packages/bp-common (dependencia local via path-repository):
#   docker build -f services/svc-notificaciones/Dockerfile -t bp/svc-notificaciones .
#
# Worker puro, igual que svc-auditoria: no sirve HTTP, no necesita
# Octane/Swoole. El proceso principal es el comando de consumo de SQS.

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

# migrate/notifications:setup-infrastructure son idempotentes -- se corren
# en cada arranque para dejar su propia cola SQS+DLQ, regla de EventBridge
# y tabla DynamoDB listas sin un paso manual aparte.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan notifications:setup-infrastructure && php artisan notifications:consume"]
