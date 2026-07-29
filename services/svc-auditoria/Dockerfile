# Se construye con el contexto en la RAIZ del monorepo, para poder copiar
# packages/bp-common (dependencia local via path-repository):
#   docker build -f services/svc-auditoria/Dockerfile -t bp/svc-auditoria .
#
# A diferencia de los servicios HTTP (BFFs, Datos Basicos, Movimientos,
# Transferencias), este es un worker puro: no sirve trafico HTTP, asi que
# no necesita Octane/Swoole -- el proceso principal del contenedor es el
# comando de consumo de SQS (equivalente a un "Laravel Horizon Worker" del
# diagrama de contenedores).

FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev pkg-config \
    && docker-php-ext-install pcntl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY packages/bp-common packages/bp-common
COPY services/svc-auditoria services/svc-auditoria

WORKDIR /app/services/svc-auditoria

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    && composer dump-autoload --optimize

# migrate/audit:setup-infrastructure son idempotentes -- se corren en cada
# arranque para que `docker compose up` deje la cola SQS+DLQ, la regla de
# EventBridge, la tabla DynamoDB y el bucket S3 listos sin un paso manual.
CMD ["sh", "-c", "touch database/database.sqlite && php artisan migrate --force && php artisan audit:setup-infrastructure && php artisan audit:consume"]
