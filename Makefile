COMPOSE ?= docker compose

.PHONY: up down key fresh test lint

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

# Генерирует APP_KEY в корневой .env (его читает контейнер через env_file).
# Запускать один раз после `cp .env.example .env` и до `make up`.
key:
	@if grep -q '^APP_KEY=$$' .env; then \
		k=$$($(COMPOSE) run --rm --no-deps -T backend php artisan key:generate --show --no-ansi); \
		sed -i.bak "s|^APP_KEY=.*|APP_KEY=$$k|" .env && rm -f .env.bak; \
		echo "APP_KEY set in .env"; \
	else \
		echo "APP_KEY already set, skipping"; \
	fi

fresh:
	$(COMPOSE) exec -T backend php artisan migrate:fresh --seed

test:
	$(COMPOSE) exec -T backend php artisan test
	$(COMPOSE) exec -T frontend npm run test:run

lint:
	$(COMPOSE) exec -T backend ./vendor/bin/pint --test
	$(COMPOSE) exec -T backend ./vendor/bin/phpstan analyse
	$(COMPOSE) exec -T frontend npm run lint
	$(COMPOSE) exec -T frontend npm run format:check
	$(COMPOSE) exec -T frontend npm run type-check
	$(COMPOSE) exec -T frontend npm run build
