# DocSign Hub

DocSign Hub — демонстрационный сервис электронного подписания документов (envelope → участники →
последовательная подпись → audit trail). Pet-проект: Laravel-бэкенд с доменной логикой, задел под событийную архитектуру (очереди/outbox) и Docker-инфраструктуру.

> Это demo, **не** юридически значимая система ЭЦП. Подпись здесь — демонстрационная (хеш-привязка к снимку
> содержимого), без реальной криптографии/КЭП.

## Статус

Бэкенд закрывает основной поток подписания: аутентификация, документы и строго последовательное подписание
по модели «capability или identity». Frontend пока — каркас с проверкой API. События в RabbitMQ, audit в
MongoDB, полноценный UI и email-верификация — следующие шаги (см. [Roadmap](#roadmap)).

## Стек

- **Backend:** PHP 8.4, Laravel 12, PostgreSQL, Redis (rate-limit/cache/locks),
  RabbitMQ (события, планируется), MongoDB (audit, планируется), Mailpit (почта в dev).
- **Frontend:** Vue 3, TypeScript, Vite, Tailwind CSS, TanStack Query, Pinia, shadcn-vue-ready структура.
- **Инфраструктура/инструменты:** Docker Compose (+ отдельный scheduler-контейнер), nginx, Makefile, PHPUnit, Pint,
  PHPStan/Larastan, OpenAPI/Swagger (Scramble).

## Возможности (реализовано)

**Аутентификация**
- Регистрация, логин, логаут, профиль (Laravel Sanctum, Bearer-токены).
- Пара **access + refresh**: короткий access для API, долгий refresh для обновления; они невзаимозаменяемы
  (token abilities). `/auth/refresh` ротирует refresh с детекцией переиспользования (отзыв всей сессии),
  `logout` рвёт обе стороны. Пароли и токены хранятся только хешем; rate-limit на auth и на всём API.

**Документы**
- CRUD черновиков, участники (signer/viewer), отправка на подписание (`draft → pending`), отмена, продление
  дедлайна, история статусов (пагинация + сортировка).
- Документ адресуется по публичному **ULID**, а не по внутреннему числовому id.
- Дедлайн фиксируется при отправке (явный `expires_at` или дефолтный TTL); на этот же момент истекают
  signing-токены. Авто-экспирация просроченных — команда `documents:expire` под ежедневным расписанием.
- Доменный слой: PHP Enums + явная карта переходов статусов, тонкие контроллеры → Actions/Services →
  Resources → Policies (управляет только владелец).

**Подписание**
- Строго последовательное (`signing_order`), идемпотентное. Внешний участник подписывает по одноразовой
  ссылке из письма (**capability**), зарегистрированный — только под своим логином (**identity**);
  утёкшей ссылки для account-bound участника недостаточно.
- Переходы `pending → partially_signed → signed`; фиксируются `signature_hash`, ip/user-agent и снимок
  содержимого (`content_hash`). Приглашения уходят подписантам на email (в dev — Mailpit); отправителю
  токены не возвращаются.

**Прочее**
- `GET /api/v1/health`, интерактивная API-документация на `/docs/api` (генерируется из кода), отрисованные
  диаграммы на `/docs/diagrams`.
- Базовый frontend-экран с проверкой API health.

## Roadmap

| Дальше | Что |
| --- | --- |
| Email verification | `MustVerifyEmail`, signed-URL верификация, гейт `verified` на чувствительные действия |
| RabbitMQ + outbox + audit | доменные события через transactional outbox → RabbitMQ; audit в MongoDB; уведомления |
| Frontend | дашборд документов, public sign page, viewer read-only, состояния loading/empty/error |
| CI, docs, cleanup | GitHub Actions (+ GitLab CI), финальная документация |

Будущие треки (поиск/Elasticsearch, observability, k8s, микросервисы/gRPC) — за рамками MVP.

## Запуск

```bash
cp .env.example .env
make key   # генерирует APP_KEY в .env (один раз, до запуска)
make up
make fresh
```

Без `make`:

```bash
cp .env.example .env
# сгенерировать APP_KEY и вписать значение в строку APP_KEY= в .env:
docker compose run --rm --no-deps backend php artisan key:generate --show
docker compose up -d --build
docker compose exec backend php artisan migrate:fresh --seed
```

`APP_KEY` хранится в корневом `.env` — именно его контейнер подключает через `env_file`.

После `make fresh` доступен демо-пользователь `owner@docsign.test` / `password`. Ссылки на подписание после
`send` приходят подписантам в Mailpit.

## Локальные URL

| Сервис | URL |
| --- | --- |
| Frontend | http://localhost:3000 |
| API health | http://localhost:8080/api/v1/health |
| API docs (OpenAPI/Swagger) | http://localhost:8080/docs/api |
| Диаграммы (отрисованные) | http://localhost:8080/docs/diagrams |
| RabbitMQ (`docsign` / `docsign`) | http://localhost:15672 |
| Mailpit | http://localhost:8025 |
| PostgreSQL | `localhost:5432` |
| MongoDB | `localhost:27017` |

## API

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | — | Проверка доступности API |
| POST | `/api/v1/auth/register` | — | Регистрация, возвращает пару access + refresh |
| POST | `/api/v1/auth/login` | — | Логин, возвращает пару access + refresh |
| POST | `/api/v1/auth/refresh` | Bearer (refresh) | Обменять refresh на новую пару (ротация + reuse-detection) |
| POST | `/api/v1/auth/logout` | Bearer (access) | Отзыв всей сессии (access + refresh) |
| GET | `/api/v1/me` | Bearer (access) | Текущий пользователь |
| GET | `/api/v1/documents` | Bearer | Список документов владельца (пагинация; фильтр `?status=`) |
| POST | `/api/v1/documents` | Bearer | Создать документ (draft) |
| GET | `/api/v1/documents/{document}` | Bearer | Документ с участниками |
| PATCH | `/api/v1/documents/{document}` | Bearer | Изменить draft |
| DELETE | `/api/v1/documents/{document}` | Bearer | Удалить draft |
| POST | `/api/v1/documents/{document}/parties` | Bearer | Добавить участника (draft) |
| DELETE | `/api/v1/documents/{document}/parties/{party}` | Bearer | Удалить участника (draft) |
| POST | `/api/v1/documents/{document}/send` | Bearer | Отправить на подписание |
| POST | `/api/v1/documents/{document}/cancel` | Bearer | Отменить документ |
| PATCH | `/api/v1/documents/{document}/deadline` | Bearer | Продлить дедлайн активного документа |
| GET | `/api/v1/documents/{document}/events` | Bearer | История статусов (пагинация; `?sort=asc`) |
| GET | `/api/v1/signing/{token}` | — | Контекст подписания по одноразовому токену (публичный) |
| POST | `/api/v1/signing/{token}/sign` | — / Bearer | Подписать по токену (account-bound — с логином) |
| GET | `/api/v1/signing-requests` | Bearer | Документы, ожидающие моей подписи |
| POST | `/api/v1/signing-requests/{party}/sign` | Bearer | Подписать своё участие из дашборда |

`{document}` — публичный ULID (поле `id` в ответе), не внутренний числовой id.

`register`/`login`/`refresh` возвращают `{ user, tokens: { token_type, access_token, access_expires_at,
refresh_token, refresh_expires_at } }`. Для API шлём `Authorization: Bearer <access_token>`, для
`/auth/refresh` — `Bearer <refresh_token>`. Полная интерактивная спецификация — на `/docs/api`.

## Архитектура

Модульный Laravel-монолит, организованный как bounded contexts под `app/Domain/<Context>` (`Documents`,
`Users`). Контроллеры тонкие: валидация — в Form Requests, бизнес-логика — в Actions/Services, сериализация —
в API Resources, доступ — через Policies. Смена статуса документа всегда идёт через `DocumentStatusService`
по явной карте переходов и пишет строку в `document_status_history` в одной транзакции.

PostgreSQL — источник истины (пользователи, документы, участники, подписи, токены, история). Redis — точечно
(rate-limit, cache, short-lived locks). RabbitMQ подключается через outbox (доменное действие в транзакции
пишет бизнес-данные и outbox-строку, отдельный publisher шлёт событие в exchange `docsign.events`); MongoDB —
для audit events. Это задел: кросс-контекстные эффекты идут через доменные события и контракты, поэтому
notifications/audit позже можно вынести в отдельные сервисы, не трогая ядро.

Подробнее — [docs/architecture.md](docs/architecture.md); диаграммы (ERD, машина состояний, sequence
отправки/подписания, deployment, messaging) — [docs/diagrams.md](docs/diagrams.md) (рендерятся на GitHub и на
`/docs/diagrams`).

## Структура репозитория

```
backend/    Laravel 12 API (app/Domain, app/Http, config, database, tests)
frontend/   Vue 3 + TS + Vite (каркас)
contracts/  версионированные контракты событий для RabbitMQ (задел под Этап 5)
docs/       architecture.md, diagrams.md
infra/      Docker-обвязка (nginx, php Dockerfile)
docker-compose.yml, Makefile, .env.example
```

## Тесты и проверки

```bash
make test
make lint
```

Локально без Docker (из `backend/`, при совместимом PHP):

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

Frontend (из `frontend/`):

```bash
npm run type-check
npm run build
```

## Осознанные ограничения

- Это demo, не юридически значимая система ЭЦП; подпись демонстрационная.
- События в RabbitMQ (outbox publisher) и audit writer в MongoDB ещё не подключены — добавляются следующим шагом.
- Приглашения на подписание уходят в Mailpit (локально); наружу реальная почта/SMS не отправляются.
- Frontend — каркас (экран health-check); полноценный UI — отдельный этап.
- Go-сервис, Kubernetes и production-grade retry/DLQ не входят в основной MVP.
