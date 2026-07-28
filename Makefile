.PHONY: up down logs ps restart

up:
	docker compose up -d
	@echo "Entorno local levantado. Usa 'make logs' para ver salida o 'make ps' para el estado."

down:
	docker compose down

logs:
	docker compose logs -f

ps:
	docker compose ps

restart:
	docker compose down
	docker compose up -d
