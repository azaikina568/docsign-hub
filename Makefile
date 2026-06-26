COMPOSE ?= docker compose

.PHONY: help up down restart rebuild reset fe-reset key fresh \
        test test-backend test-frontend test-e2e verify lint

help: ## список целей
	@grep -hE '^[a-z0-9-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

# --- Стек (БД живёт в именованных томах: переживает up/down/restart; стирается только `reset`/`down -v`) ---

up: ## собрать и поднять стек (БД сохраняется)
	$(COMPOSE) up -d --build

down: ## остановить стек (тома/БД сохраняются)
	$(COMPOSE) down

restart: ## перезапустить контейнеры — подхватить свежий исходник, БД не трогать
	$(COMPOSE) restart

rebuild: ## пересобрать образы и пересоздать контейнеры (БД сохраняется)
	$(COMPOSE) up -d --build --force-recreate

fe-reset: ## пересоздать фронт и кэш Vite (БД не трогать) — застрявший кэш / ПОСЛЕ добавления npm-зависимости
	-$(COMPOSE) exec -T frontend sh -c "rm -rf node_modules/.vite"
	$(COMPOSE) restart frontend

reset: ## ПОЛНЫЙ сброс: снести тома (БД, node_modules, vendor-кэш) и поднять чистый стек + сид
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build
	$(MAKE) fresh

# Генерирует APP_KEY в корневой .env (его читает контейнер через env_file).
# Запускать один раз после `cp .env.example .env` и до `make up`.
key: ## сгенерировать APP_KEY в .env (один раз, до up)
	@if grep -q '^APP_KEY=$$' .env; then \
		k=$$($(COMPOSE) run --rm --no-deps -T backend php artisan key:generate --show --no-ansi); \
		sed -i.bak "s|^APP_KEY=.*|APP_KEY=$$k|" .env && rm -f .env.bak; \
		echo "APP_KEY set in .env"; \
	else \
		echo "APP_KEY already set, skipping"; \
	fi

fresh: ## пересоздать схему БД и засидить (контейнеры не трогает)
	$(COMPOSE) exec -T backend php artisan migrate:fresh --seed

# --- Тесты (по блокам и все сразу) ---

test: test-backend test-frontend ## все юнит/feature тесты (backend + frontend), без e2e

test-backend: ## backend: php artisan test (sqlite in-memory, без внешних сервисов)
	$(COMPOSE) exec -T backend php artisan test

test-frontend: ## frontend: Vitest (happy-dom + MSW, без бэкенда)
	$(COMPOSE) exec -T frontend npm run test:run

test-e2e: ## frontend e2e: Playwright против поднятого стека (свой Vite, браузер на хосте)
	cd frontend && npm run e2e

# --- Качество ---

lint: ## стиль + статанализ + типы + сборка (backend + frontend)
	$(COMPOSE) exec -T backend ./vendor/bin/pint --test
	$(COMPOSE) exec -T backend ./vendor/bin/phpstan analyse
	$(COMPOSE) exec -T frontend npm run lint
	$(COMPOSE) exec -T frontend npm run format:check
	$(COMPOSE) exec -T frontend npm run type-check
	$(COMPOSE) exec -T frontend npm run build

verify: lint test ## быстрый полный гейт перед коммитом: качество + все юнит/feature тесты (без e2e)
