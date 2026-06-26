# DocSign Hub

DocSign Hub — демонстрационный сервис электронного подписания документов (envelope → участники →
последовательная подпись → audit trail). Pet-проект: Laravel-бэкенд с доменной логикой, задел под
событийную архитектуру (очереди/outbox), Vue 3 SPA и Docker-инфраструктуру.

> Это demo, **не** юридически значимая система ЭЦП. Подпись здесь — демонстрационная (хеш-привязка к снимку
> содержимого), без реальной криптографии/КЭП.

**Навигация:** [Статус](#статус) · [Стек](#стек) · [Возможности](#возможности-реализовано) ·
[Запуск](#запуск) · [API](#api) · [Архитектура](#архитектура) · [Тесты](#тесты-и-проверки) ·
[Ограничения](#осознанные-ограничения) · [Roadmap](#roadmap)

## Статус

Сквозной поток работает целиком — от регистрации до подписанного документа. **Бэкенд:** аутентификация (с
верификацией email), документы и строго последовательное подписание по модели «capability или identity»;
доменные события идут через transactional outbox в RabbitMQ, consumer'ы рассылают приглашения по очереди и
пишут audit в MongoDB. **Фронтенд:** полноценный SPA (auth с прозрачным refresh, документы, публичное
подписание, верификация email, единые toast'ы и валидация форм, локализация en/ru), покрыт юнит/компонентными
и сквозным e2e (Playwright) тестами.

Дальше — CI (см. [Roadmap](#roadmap)).

## Стек

- **Backend:** PHP 8.4, Laravel 12, PostgreSQL, Redis (rate-limit/cache/locks),
  RabbitMQ (доменные события через outbox), MongoDB (audit-журнал), Mailpit (почта в dev).
- **Frontend:** Vue 3, TypeScript, Vite, Tailwind CSS (компоненты вручную), TanStack Query, Pinia, zod, vue-i18n (en/ru);
  Vitest + Vue Test Utils + MSW, Playwright (e2e), ESLint + Prettier.
- **Инфраструктура/инструменты:** Docker Compose (+ отдельный scheduler-контейнер), nginx, Makefile, PHPUnit, Pint,
  PHPStan/Larastan, OpenAPI/Swagger (Scramble).

## Возможности (реализовано)

**Аутентификация**
- Регистрация, логин, логаут, профиль (Laravel Sanctum, Bearer-токены).
- Пара **access + refresh**: короткий access для API, долгий refresh для обновления; они невзаимозаменяемы
  (token abilities). `/auth/refresh` ротирует refresh с детекцией переиспользования (отзыв всей сессии),
  `logout` рвёт обе стороны. Пароли и токены хранятся только хешем; rate-limit на auth и на всём API.
- **Верификация email**: при регистрации уходит письмо со подписанной ссылкой (подпись = доказательство
  контроля над ящиком), есть повторная отправка с тугим rate-limit. Создание документов — только с
  подтверждённым email (точка расширения под invite-only/подписку).

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
- **Поэтапная рассылка**: при отправке приглашение получает только первый по очереди; следующего зовёт
  consumer уведомлений после подписи предыдущего. Токен перевыпускается при доставке — emailed-ссылка
  всегда свежая и одноразовая, в БД лежит только хеш.

**События (messaging)**
- Доменные события (`document.created/sent/signed/completed/cancelled/expired`) пишутся в transactional
  outbox в одной транзакции с бизнес-данными и публикуются worker'ом в RabbitMQ (`docsign.events`) с
  ретраями/backoff. Форма каждого события зафиксирована JSON Schema (`contracts/events/v1`).
- Два consumer'а читают события: **уведомления** (приглашения по очереди, письмо владельцу об истечении) и
  **audit** (неизменяемый журнал в MongoDB, `audit_events`). At-least-once + дедуп по `event_id` (inbox);
  отвергнутые сообщения уходят в dead-letter (`docsign.dlq`).

**Прочее**
- `GET /api/v1/health`, интерактивная API-документация на `/docs/api` (генерируется из кода), отрисованные
  диаграммы на `/docs/diagrams`.
- Frontend (Vue 3 SPA): аутентификация — register/login/logout, **прозрачный refresh** access-токена по 401
  (один refresh на пачку параллельных запросов), защищённые роуты, баннер верификации email.
- Frontend документы: список с фильтром по статусу/бейджами/пагинацией и пометкой «истекает скоро», создание
  черновика, детальная страница (участники, добавление/удаление, отправка, отмена, продление дедлайна, таймлайн
  истории) и **индикатор очереди подписания** («подписано N из M» + кого ждём). Server-state на TanStack Query.
- Frontend подписание: публичная страница по ссылке из письма (`/signing/{token}` — контекст, прогресс, подпись по
  capability или identity), дашборд «To sign» (`/signing-requests`), страница подтверждения email (`/verify-email/...`)
  с resend. Ссылки в письмах ведут на SPA, а не на API.
- Frontend UX: единые toast-уведомления (успех/ошибка действий), нормализация ошибок бэка по кодам
  (401/403/404/409/410/422/429/5xx → понятный текст, без «голых» 500), клиентская валидация форм (zod) с подсказкой
  требований к паролю, «дублировать» терминальный документ в новый draft, mobile-first адаптив.
- Frontend локализация: vue-i18n со словарями en/ru, переключатель языка (выбор сохраняется в localStorage,
  язык определяется по браузеру при первом заходе), формат дат по локали, плюрализация (в т.ч. русские формы).
- Frontend-тесты и стандарты: Vitest + Vue Test Utils + MSW (юнит/компонентные/интеграционные — валидация, нормализация
  ошибок, тосты, формат, стор, прозрачный refresh клиента), Playwright e2e сквозного сценария подписания против живого
  стека; ESLint (vue/ts) + Prettier (`npm run lint` / `format:check`).

## Roadmap

| Дальше | Что |
| --- | --- |
| CI, docs, cleanup | GitHub Actions (+ GitLab CI), каталог событий (AsyncAPI), финальная документация |

После этого — фазированная пост-MVP дорожка: управление аккаунтом (профиль/удаление с анонимизацией PII),
security-hardening, observability (Sentry/Prometheus), контент документов с файлами и юридической ретенцией
(S3/MinIO, immutable/WORM + legal hold), локализация писем, real-time (Reverb), поиск (Elasticsearch), админка,
а в Тир-2 — оптимизация образов, микросервисы и Go-сервис (конвертация + проверка подписи), gRPC, Kubernetes.
Всё это — за рамками текущего MVP.

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
| RabbitMQ (`docsign`) | http://localhost:15672 |
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
| GET | `/api/v1/auth/verify/{id}/{hash}` | подписанная ссылка | Подтвердить email по ссылке из письма |
| POST | `/api/v1/auth/verification/resend` | Bearer (access) | Повторно отправить ссылку подтверждения |
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
(rate-limit, cache, short-lived locks). Доменные события идут через **transactional outbox**: действие в своей
транзакции пишет бизнес-данные и строку в `outbox_messages` (атомарно, без потерь), а отдельный worker-publisher
(`outbox:publish`) шлёт их в topic-exchange `docsign.events` с ретраями/backoff. Форма каждого события
зафиксирована JSON Schema (`contracts/events/v1`). Два consumer'а (`messaging:consume notifications|audit`)
читают события: уведомления рассылают приглашения по очереди и пишут владельцу об истечении, audit складывает
неизменяемый журнал в MongoDB. Доставка at-least-once, идемпотентность — inbox по `event_id`; необрабатываемые
сообщения уходят в dead-letter. Это задел под микросервисы: кросс-контекстные эффекты идут через
версионированные контракты событий, поэтому notifications/audit позже можно вынести в отдельные сервисы,
не трогая ядро.

Подробнее — [docs/architecture.md](docs/architecture.md); диаграммы (ERD, машина состояний, sequence
отправки/подписания/рассылки, deployment, messaging) — [docs/diagrams.md](docs/diagrams.md) (рендерятся на
GitHub и на `/docs/diagrams`).

## Структура репозитория

```
backend/    Laravel 12 API (app/Domain, app/Http, config, database, tests)
frontend/   Vue 3 + TS + Vite SPA (api-клиент, refresh, stores, router, pages, i18n en/ru; тесты Vitest + MSW, ESLint/Prettier)
contracts/  версионированные контракты событий (JSON Schema) для RabbitMQ
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
npm run lint          # ESLint (vue/ts)
npm run format:check  # Prettier
npm run type-check
npm run test:run      # Vitest (юнит/компонентные/MSW)
npm run build
```

End-to-end (Playwright, против поднятого стека `make up`):

```bash
npx playwright install chromium   # один раз
npm run e2e                        # сквозной сценарий подписания + смоук гарда
```

`npm run e2e` сам поднимает фронт (Vite) и гоняет браузер против API/Mailpit из Docker; основной тест проходит
весь путь: регистрация → верификация email из письма → черновик → участники → отправка → поэтапная подпись
(приглашения приходят по очереди через consumer) → документ подписан.

## Осознанные ограничения

- Это demo, не юридически значимая система ЭЦП; подпись демонстрационная.
- Приглашения и уведомления уходят в Mailpit (локально); наружу реальная почта/SMS не отправляются.
- Publisher и consumer'ы — по одному инстансу (без конкурентного вычитывания); горизонтальное
  масштабирование (`FOR UPDATE SKIP LOCKED`, несколько воркеров) описано, но намеренно не включено.
- Audit-журнал в MongoDB пишется append-only; UI/поиск по нему — будущий трек.
- Frontend покрыт юнит/компонентными тестами (Vitest + MSW) и сквозным e2e (Playwright) основного сценария; локализован
  на en/ru (vue-i18n). Локализация писем/бэкенда (язык по получателю) — будущий трек.
- Go-сервис, Kubernetes и production-grade retry/DLQ не входят в основной MVP.
