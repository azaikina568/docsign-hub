# DocSign Hub

DocSign Hub - демонстрационный сервис для сценария электронного подписания документов. Проект собирается как pet project для GitHub/LinkedIn: Laravel API, Vue dashboard, Docker-инфраструктура и понятный задел под доменную логику, очереди и audit trail.

Сейчас готовы каркас backend/frontend, аутентификация по токенам (Sanctum) и доменная часть документов: CRUD, участники, отправка на подписание и отмена с историей статусов. Публичное подписание по токену и события в RabbitMQ добавляются отдельными шагами.

## Стек

- Backend: PHP 8.4 в Docker, Laravel 12, PostgreSQL, Redis, RabbitMQ, MongoDB, Mailpit.
- Frontend: Vue 3, TypeScript, Vite, Tailwind CSS, TanStack Query, Pinia, shadcn-vue-ready structure.
- Infra: Docker Compose (включая отдельный scheduler-контейнер), nginx, Makefile, PHPUnit, Pint, PHPStan/Larastan, OpenAPI/Swagger (Scramble).

Диаграммы (ERD, машина состояний, sequence отправки, развёртывание): [docs/diagrams.md](docs/diagrams.md) — рендерятся на GitHub и как страница приложения на `/docs/diagrams`.

## Что реализовано

- Laravel 12 в `backend/`.
- Vue 3 + TypeScript + Vite в `frontend/`.
- Аутентификация по токенам (Laravel Sanctum): регистрация, логин, логаут, профиль.
- Rate limit на auth-endpoints и на всём аутентифицированном API, пароли хешируются, токены хранятся только хешем.
- Документы: CRUD, участники, отправка на подписание (`draft → pending`) и отмена, история статусов (пагинация + сортировка).
- Документ адресуется в API по публичному ULID (а не по внутреннему числовому id).
- Дедлайн документа фиксируется при отправке (явный `expires_at` владельца или дефолтный TTL); на этот же момент истекают signing-токены.
- Авто-экспирация: команда `documents:expire` под ежедневным расписанием (отдельный scheduler-контейнер) переводит просроченные документы в `expired`.
- Доменный слой: PHP Enums + явная карта переходов статусов, Actions/Services, Policies (управляет только владелец).
- Подписанты идентифицируются по email (аккаунт необязателен) и опционально связываются с пользователем системы.
- При отправке приглашения уходят подписантам на email (локально — Mailpit); отправителю signing-токены не возвращаются, хранятся только хешем и со сроком жизни.
- API endpoint `GET /api/v1/health`.
- Интерактивная API-документация (OpenAPI/Swagger) на `/docs/api`, генерируется из кода.
- Базовый frontend-экран с проверкой API health.
- Docker Compose для backend, nginx, frontend, PostgreSQL, Redis, RabbitMQ, MongoDB и Mailpit.
- Корневой `.env.example`, `.gitignore`, Makefile и краткая архитектурная документация.

## Запуск

```bash
cp .env.example .env
make key   # генерирует APP_KEY в .env (один раз, до запуска)
make up
make fresh
```

Если `make` недоступен:

```bash
cp .env.example .env
# сгенерировать APP_KEY и вписать значение в строку APP_KEY= в .env:
docker compose run --rm --no-deps backend php artisan key:generate --show
docker compose up -d --build
docker compose exec backend php artisan migrate:fresh --seed
```

`APP_KEY` хранится в корневом `.env`, потому что именно его контейнер подключает через `env_file`.

## Локальные URL

- Frontend: http://localhost:3000
- API health: http://localhost:8080/api/v1/health
- API docs (OpenAPI/Swagger, интерактивные): http://localhost:8080/docs/api
- Диаграммы (отрисованные): http://localhost:8080/docs/diagrams
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
| GET | `/api/v1/documents` | Bearer | Список документов владельца (пагинация; фильтр `?status=`) |
| POST | `/api/v1/documents` | Bearer | Создать документ (draft) |
| GET | `/api/v1/documents/{document}` | Bearer | Документ с участниками |
| PATCH | `/api/v1/documents/{document}` | Bearer | Изменить draft |
| DELETE | `/api/v1/documents/{document}` | Bearer | Удалить draft |
| POST | `/api/v1/documents/{document}/parties` | Bearer | Добавить участника (draft) |
| DELETE | `/api/v1/documents/{document}/parties/{party}` | Bearer | Удалить участника (draft) |
| POST | `/api/v1/documents/{document}/send` | Bearer | Отправить на подписание |
| POST | `/api/v1/documents/{document}/cancel` | Bearer | Отменить документ |
| GET | `/api/v1/documents/{document}/events` | Bearer | История статусов (пагинация; `?sort=asc` для хронологии) |

`{document}` — публичный ULID документа (поле `id` в ответе), не внутренний числовой id.

После `make fresh` доступен демо-пользователь: `owner@docsign.test` / `password`.
Ссылки на подписание после `send` приходят подписантам в Mailpit (http://localhost:8025).

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
- Пока нет публичного подписания по токену, outbox publisher и audit writer - они добавляются следующими шагами.
- Приглашения на подписание уходят в Mailpit (локально); наружу реальная почта/SMS не отправляются.
- Go-сервис, Kubernetes и production-grade retry/DLQ не входят в основной MVP.
