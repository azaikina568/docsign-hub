# DocSign Hub

DocSign Hub - демонстрационный сервис для сценария электронного подписания документов. Проект собирается как pet project для GitHub/LinkedIn: Laravel API, Vue dashboard, Docker-инфраструктура и понятный задел под доменную логику, очереди и audit trail.

Сейчас готовы каркас backend/frontend, health endpoint, локальная инфраструктура и аутентификация по токенам (Sanctum). Бизнес-логика документов и подписания добавляется отдельными шагами.

## Стек

- Backend: PHP 8.4 в Docker, Laravel 12, PostgreSQL, Redis, RabbitMQ, MongoDB, Mailpit.
- Frontend: Vue 3, TypeScript, Vite, Tailwind CSS, TanStack Query, Pinia, shadcn-vue-ready structure.
- Infra: Docker Compose, nginx, Makefile, PHPUnit, Pint, PHPStan/Larastan.

## Что реализовано

- Laravel 12 в `backend/`.
- Vue 3 + TypeScript + Vite в `frontend/`.
- Аутентификация по токенам (Laravel Sanctum): регистрация, логин, логаут, профиль.
- Rate limit на auth-endpoints, пароли хешируются, токены хранятся только хешем.
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

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | — | Проверка доступности API |
| POST | `/api/v1/auth/register` | — | Регистрация, возвращает Bearer-токен |
| POST | `/api/v1/auth/login` | — | Логин, возвращает Bearer-токен |
| POST | `/api/v1/auth/logout` | Bearer | Отзыв текущего токена |
| GET | `/api/v1/me` | Bearer | Текущий пользователь |

После `make fresh` доступен демо-пользователь: `owner@docsign.test` / `password`.

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
- Пока нет документов, подписания, outbox publisher и audit writer - они добавляются следующими шагами.
- Email/SMS не отправляются наружу; для будущих demo-уведомлений подготовлен Mailpit.
- Go-сервис, Kubernetes и production-grade retry/DLQ не входят в основной MVP.
