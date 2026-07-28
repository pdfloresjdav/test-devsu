# Se construye con el contexto en la RAIZ del monorepo, para poder copiar
# packages/bp-common (dependencia local via path-repository):
#   docker build -f services/svc-transferencias/Dockerfile -t bp/svc-transferencias .

FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libbrotli-dev pkg-config default-mysql-client \
    && docker-php-ext-install pcntl sockets zip pdo_mysql \
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

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
