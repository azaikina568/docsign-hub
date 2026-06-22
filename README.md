# DocSign Hub

DocSign Hub - демонстрационный сервис для сценария электронного подписания документов. Проект собирается как pet project для GitHub/LinkedIn: Laravel API, Vue dashboard, Docker-инфраструктура и понятный задел под доменную логику, очереди и audit trail.

Сейчас реализован только bootstrap: каркас backend/frontend, health endpoint, локальная инфраструктура и документация запуска. Бизнес-логика подписания будет добавляться отдельными шагами.

## Стек

- Backend: PHP 8.4 в Docker, Laravel 12, PostgreSQL, Redis, RabbitMQ, MongoDB, Mailpit.
- Frontend: Vue 3, TypeScript, Vite, Tailwind CSS, TanStack Query, Pinia, shadcn-vue-ready structure.
- Infra: Docker Compose, nginx, Makefile, PHPUnit, Pint, PHPStan/Larastan.

## Что реализовано

- Laravel 12 в `backend/`.
- Vue 3 + TypeScript + Vite в `frontend/`.
- API endpoint `GET /api/v1/health`.
- Базовый frontend-экран с проверкой API health.
- Docker Compose для backend, nginx, frontend, PostgreSQL, Redis, RabbitMQ, MongoDB и Mailpit.
- Корневой `.env.example`, `.gitignore`, Makefile и краткая архитектурная документация.

## Запуск

```bash
cp .env.example .env
make up
make fresh
```

Если `make` недоступен:

```bash
docker-compose up -d --build
docker-compose exec backend php artisan key:generate --force
docker-compose exec backend php artisan migrate:fresh --seed
```

## Локальные URL

- Frontend: http://localhost:3000
- API health: http://localhost:8080/api/v1/health
- RabbitMQ: http://localhost:15672
- Mailpit: http://localhost:8025
- MongoDB: `localhost:27017`
- PostgreSQL: `localhost:5432`

RabbitMQ default credentials for local development: `docsign` / `docsign`.

## API

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/api/v1/health` | Проверка доступности API |

## Архитектура

Backend остается модульным Laravel-монолитом: контроллеры должны быть тонкими, бизнес-логика будет жить в Actions/Services, валидация - в Form Requests, сериализация - в API Resources, доступ - через Policies.

PostgreSQL будет источником истины для пользователей, документов, участников, подписей, токенов и истории статусов. Redis планируется использовать точечно: rate limit, cache и short-lived locks.

RabbitMQ будет подключен через outbox: доменное действие в транзакции пишет бизнес-данные и outbox row, отдельный publisher отправляет событие в exchange `docsign.events`. MongoDB предназначена для audit events и событийной истории, а не для основных бизнес-данных.

## Тесты и проверки

```bash
make test
make lint
```

Локально без Docker можно запускать backend-проверки из `backend/`, если установлен совместимый PHP:

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

Frontend:

```bash
npm run type-check
npm run build
```

## Осознанные ограничения

- Это demo project, не юридически значимая система ЭЦП.
- На bootstrap-шаге нет регистрации, документов, подписания, outbox publisher и audit writer.
- Email/SMS не отправляются наружу; для будущих demo-уведомлений подготовлен Mailpit.
- Go-сервис, Kubernetes и production-grade retry/DLQ не входят в основной MVP.
