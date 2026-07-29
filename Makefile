.PHONY: up down logs ps restart dev dev-down dev-logs dev-ps dev-restart

# Solo infraestructura (MySQL, Redis, LocalStack, mock-oidc) -- el flujo
# usado en las Fases 2 a 10, para correr cada servicio a mano con
# `php artisan serve`/`octane:start` mientras se desarrolla.
up:
	docker compose up -d
	@echo "Infraestructura local levantada. Usa 'make logs' para ver salida o 'make ps' para el estado."

down:
	docker compose --profile services down

logs:
	docker compose --profile services logs -f

ps:
	docker compose --profile services ps

restart:
	docker compose --profile services down
	docker compose up -d

# Infraestructura + los 7 servicios backend, cada uno buildeado desde su
# Dockerfile (Fase 11: orquestacion end-to-end local). Cada contenedor
# corre sus migraciones/comandos de provision idempotentes al arrancar
# (ver los Dockerfile de cada servicio) -- no hace falta ningun paso manual
# aparte de este comando.
dev:
	docker compose --profile services up -d --build
	@echo "Entorno completo levantado (infra + 7 servicios backend). Usa 'make dev-ps' para ver el estado o 'make dev-logs' para seguir la salida."

dev-down:
	docker compose --profile services down

dev-logs:
	docker compose --profile services logs -f

dev-ps:
	docker compose --profile services ps

dev-restart:
	docker compose --profile services down
	docker compose --profile services up -d --build
