COMPOSE ?= docker-compose

.PHONY: up down fresh test lint

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

fresh:
	$(COMPOSE) exec -T backend php artisan key:generate --force
	$(COMPOSE) exec -T backend php artisan migrate:fresh --seed

test:
	$(COMPOSE) exec -T backend php artisan test

lint:
	$(COMPOSE) exec -T backend ./vendor/bin/pint --test
	$(COMPOSE) exec -T backend ./vendor/bin/phpstan analyse
	$(COMPOSE) exec -T frontend npm run type-check
	$(COMPOSE) exec -T frontend npm run build
