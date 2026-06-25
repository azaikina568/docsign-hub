# DocSign Hub — diagrams

Mermaid-диаграммы. Рендерятся прямо на GitHub, а локально открываются как страница приложения на
`/docs/diagrams` (этот же файл, отрисованный mermaid.js — по аналогии с `/docs/api`). Держим их в
синхроне с кодом: схема БД — с миграциями, state machine — с `DocumentStatus::allowedTransitions()`,
sequence — с `SendDocumentAction`, `SignDocumentAction` и consumer'ами.

Каждый раздел: сначала «что показывает», затем сама диаграмма, при необходимости — «как читать».
Содержание: [ERD](#схема-данных-erd) · [статусы документа](#жизненный-цикл-документа-state-machine) ·
[отправка на подписание](#отправка-на-подписание-sequence) · [поэтапная рассылка приглашений](#поэтапная-рассылка-приглашений-sequence) ·
[обновление токенов](#обновление-токенов-sequence) ·
[верификация email](#верификация-email-sequence) · [развёртывание](#развёртывание-контейнеры) ·
[события и очереди](#события-и-очереди).

## Схема данных (ERD)

**Что показывает:** таблицы домена и связи между ними (что на что ссылается).

**Как читать** (нотация «crow's-foot» у концов связи): `||` — ровно один, `o{` — ноль или много,
`o|` — ноль или один. Пример: «один `documents` → ноль-или-много `document_parties`». `PK` — первичный ключ,
`FK` — внешний, `UK` — уникальный. В кавычках у поля — короткий комментарий.

```mermaid
erDiagram
    users ||--o{ documents : owns
    users |o--o{ document_parties : "linked by email (nullable)"
    documents ||--o{ document_parties : has
    documents ||--o{ document_status_history : tracks
    documents ||--o{ signatures : collects
    document_parties ||--o| signature_tokens : "issued at send"
    document_parties ||--o| signatures : "produces (stage 4)"

    documents {
        bigint id PK
        ulid ulid UK "публичный id"
        bigint owner_id FK
        string title
        string status "enum DocumentStatus"
        string content_hash "nullable, заполняется при подписании"
        timestamp expires_at "nullable, дедлайн = срок токенов"
        timestamp completed_at "nullable"
        timestamps created_updated
    }
    document_parties {
        bigint id PK
        bigint document_id FK
        bigint user_id FK "nullable, связь по email"
        string name
        string email
        string role "signer|viewer"
        smallint signing_order "nullable: только у signer"
        string status "enum PartyStatus"
        timestamp signed_at "nullable"
        timestamp invited_at "nullable: приглашение доставлено"
    }
    signature_tokens {
        bigint id PK
        bigint document_party_id FK
        string token_hash UK "sha256, plain не хранится"
        timestamp expires_at "TTL"
        timestamp used_at "nullable (stage 4)"
    }
    signatures {
        bigint id PK
        bigint document_id FK
        bigint document_party_id FK "unique: одна подпись на участника"
        string signature_hash
        json signed_payload "nullable"
        string ip_address "nullable"
        text user_agent "nullable"
        timestamp signed_at
    }
    document_status_history {
        bigint id PK
        bigint document_id FK
        string from_status "nullable"
        string to_status
        string reason "nullable"
        bigint changed_by_user_id FK "nullable: null = система (expire)"
        timestamp created_at
    }
    outbox_messages {
        bigint id PK
        uuid event_id UK "дедуп публикации"
        string event_type "document.signed"
        string routing_key "document.signed.v1"
        string aggregate_id "document ULID, без FK"
        json payload "конверт события"
        string status "pending|published|failed"
        timestamp available_at "когда публиковать"
        timestamp published_at "nullable"
    }
    inbox_messages {
        bigint id PK
        string consumer "notifications|audit"
        string event_id "UK с consumer: дедуп доставки"
        timestamp processed_at
    }
```

Уникальные ключи: `documents.ulid`, `document_parties(document_id, email)`, `signature_tokens.token_hash`,
`signatures.document_party_id`, `outbox_messages.event_id`, `inbox_messages(consumer, event_id)`. Составной индекс
`documents(owner_id, status, created_at)` под список владельца; `outbox_messages(status, available_at)` под выборку
publisher'ом. `outbox_messages`/`inbox_messages` стоят особняком (без FK): ссылаются на документ по ULID и хранят
самодостаточный payload — так контекст обмена сообщениями не связан схемой с доменом и его можно вынести в отдельный
сервис. `inbox_messages` — идемпотентность consumer'ов: повторная доставка (at-least-once) того же `event_id` тому же
consumer'у не повторяет эффект.

## Жизненный цикл документа (state machine)

**Что показывает:** допустимые статусы документа и переходы между ними. `signed`/`cancelled`/`expired` —
терминальные (исходящих стрелок нет). Источник истины — `DocumentStatus::allowedTransitions()`; каждый
переход пишется в `document_status_history`.

```mermaid
stateDiagram-v2
    [*] --> draft : create
    draft --> pending : send (минимум 1 signer)
    draft --> cancelled : cancel
    pending --> partially_signed : sign (не последний)
    pending --> signed : sign последний
    pending --> cancelled : cancel
    pending --> expired : срок истёк
    partially_signed --> signed : sign последний
    partially_signed --> cancelled : cancel
    partially_signed --> expired : срок истёк
    signed --> [*]
    cancelled --> [*]
    expired --> [*]
```

## Отправка на подписание (sequence)

**Что показывает:** что происходит по `POST /documents/{ulid}/send`. Сама отправка только фиксирует
состояние и пишет событие `document.sent` в outbox; письмо подписанту уходит асинхронно — через publisher
и consumer уведомлений (см. [события и очереди](#события-и-очереди)). Голубая рамка — одна транзакция БД.

```mermaid
sequenceDiagram
    actor Owner
    participant API as SendDocumentController
    participant Act as SendDocumentAction
    participant DB as PostgreSQL

    Owner->>API: POST /documents/{ulid}/send (Bearer)
    API->>Act: execute(document, owner)
    Note over Act: проверки draft и минимум один signer
    rect rgb(238,246,255)
    Act->>DB: BEGIN
    Act->>DB: на каждого signer создаём signature_token (hash, TTL)
    Act->>DB: ставим expires_at, статус draft в pending, пишем history
    Act->>DB: пишем outbox document.sent (только метаданные)
    Act->>DB: COMMIT
    end
    API-->>Owner: 200 без токенов в ответе
```

Запрос на send не блокируется почтой: доставка вынесена за очередь. Plain-токены отправителю не
возвращаются и в payload события не попадают.

## Поэтапная рассылка приглашений (sequence)

**Что показывает:** как приглашения уходят по очереди (OPEN_QUESTIONS Q3), а не всем сразу. Consumer
уведомлений на `document.sent` зовёт первого по очереди, на каждый `document.signed` (пока документ не
завершён) — следующего. Источник истины — `NotificationsConsumer` и `SigningInvitationCoordinator`.

```mermaid
sequenceDiagram
    participant Q as queue docsign.notifications
    participant C as NotificationsConsumer
    participant Co as SigningInvitationCoordinator
    participant DB as PostgreSQL
    participant Mail as Mailpit / провайдер
    actor Signer

    Q->>C: document.sent.v1 / document.signed.v1
    C->>Co: inviteCurrentSigner(document)
    alt документ открыт, есть неподписавший signer, ещё не приглашён
        Co->>DB: текущий по очереди signer (invited_at null)
        Co->>DB: перевыпуск токена (новый hash, тот же дедлайн)
        Co->>Mail: письмо с персональной ссылкой (свежий plain-токен)
        Mail-->>Signer: приглашение подписать
        Co->>DB: invited_at = now (после успешной отправки)
    else завершён, некого звать или уже приглашён
        Note over Co: no-op
    end
```

Plain-токен не хранится, поэтому к моменту отложенной доставки его уже нет — coordinator перевыпускает
токен подписанта прямо при рассылке. Так emailed-ссылка всегда одноразовая, а в БД лежит только хеш.
Двойное приглашение исключено двумя слоями: `invited_at` на участнике (одному текущему подписанту письмо
уходит ровно раз, даже если на него указали и `sent`, и `signed`) и inbox по `event_id` (повторная доставка
того же события). Перевыпуск без `invited_at` мог бы инвалидировать уже отправленную ссылку.

## Подписание участником (sequence)

**Что показывает:** как участник подписывает (`POST /signing/{token}/sign`) строго по очереди.
Два пути идентификации: внешний — по capability-токену из письма; зарегистрированный — по своей
identity (логину). Голубая рамка — транзакция с блокировкой документа (защита от гонки параллельных
подписей). Источник истины — `SignDocumentAction`.

```mermaid
sequenceDiagram
    actor Signer
    participant API as SigningController
    participant Act as SignDocumentAction
    participant DB as PostgreSQL

    Signer->>API: POST /signing/{token}/sign
    API->>Act: execute(party, authUser, token)
    rect rgb(238,246,255)
    Act->>DB: BEGIN, блокируем документ (lockForUpdate)
    Note over Act: актёр вправе подписать (capability или identity)
    alt участник уже подписал
        Act-->>Signer: 200 та же подпись (идемпотентно)
    else документ не открыт или срок истёк
        Act-->>Signer: 409 или 410
    else не его очередь
        Note over Act: есть signer с меньшим order и без подписи
        Act-->>Signer: 409 рано подписывать
    else можно подписать
        Act->>DB: signature (hash, ip, user_agent), party в signed
        Act->>DB: токен в used_at
        Act->>DB: документ в partially_signed или signed, history
        Act->>DB: COMMIT
        Act-->>Signer: 201 подпись создана
    end
    end
```

Account-bound участник (`user_id` задан) подписывает только как он сам: capability-токена мало, нужна
identity. Зарегистрированный может прийти из дашборда — `GET /signing-requests` и
`POST /signing-requests/{party}/sign` под `auth:sanctum`.

## Обновление токенов (sequence)

**Что показывает:** обмен refresh-токена на новую пару с ротацией и детекцией переиспользования
(`POST /auth/refresh`). Access живёт коротко, refresh — долго; оба связаны «семьёй» (`family`).
Источник истины — `RefreshTokensAction`.

```mermaid
sequenceDiagram
    actor Client
    participant API as AuthController /auth/refresh
    participant Act as RefreshTokensAction
    participant DB as personal_access_tokens

    Client->>API: POST /auth/refresh (Bearer refresh, ability issue-access)
    API->>Act: execute(user, refresh)
    alt refresh уже использован (consumed)
        Act->>DB: удалить всю семью family
        Act-->>Client: 401, сессия отозвана (возможна кража)
    else первое использование
        Act->>DB: пометить refresh consumed, удалить старый access семьи
        Act->>DB: выдать новую пару access и refresh в той же family
        Act-->>Client: 200 новая пара
    end
```

Access-токен ходит в API (ability `access-api`), refresh — только на `/auth/refresh` (ability
`issue-access`); они невзаимозаменяемы. `logout` и детекция переиспользования гасят всю семью.

## Верификация email (sequence)

**Что показывает:** как подтверждается email — от регистрации до перехода по подписанной ссылке из письма.
Ссылка верификации публична: её подпись и есть capability (доказательство контроля над ящиком), поэтому
Bearer не нужен. Источник истины — `RegisterUserAction` и `EmailVerificationController`.

```mermaid
sequenceDiagram
    actor User
    participant API as AuthController /register
    participant Act as RegisterUserAction
    participant Mail as Mailpit / провайдер
    participant V as EmailVerificationController

    User->>API: POST /auth/register
    API->>Act: execute(data)
    Act->>Mail: событие Registered шлёт письмо со подписанной ссылкой
    API-->>User: 201 user (email_verified_at null) и токены
    Mail-->>User: ссылка GET /auth/verify/{id}/{hash}
    User->>V: переход по подписанной ссылке
    Note over V: middleware signed проверил подпись и срок
    alt уже подтверждён
        V-->>User: 200 already verified
    else подпись валидна и hash совпал
        V->>V: markEmailAsVerified, событие Verified
        V-->>User: 200 email verified
    end
```

Создавать документы можно только с подтверждённым email (`DocumentPolicy::create`). Повторная отправка
ссылки — `POST /auth/verification/resend` под логином, с тугим `throttle:verification`.

## Развёртывание (контейнеры)

**Что показывает:** какие контейнеры есть и кто с кем общается. Сплошные стрелки — рантайм, пунктир —
dev-почта. `publisher` вычитывает outbox в брокер; два consumer'а читают из брокера: уведомления шлют письма,
audit пишет в MongoDB.

```mermaid
flowchart LR
    Client(["Клиент / Vue SPA"]) -->|HTTP| nginx
    nginx -->|FastCGI| backend["backend (php-fpm, Laravel 12)"]
    scheduler["scheduler (schedule:work)"]
    publisher["publisher (outbox:publish)"]
    cnotif["consumer-notifications"]
    caudit["consumer-audit"]
    backend --> postgres[("PostgreSQL")]
    backend --> redis[("Redis: cache, rate-limit, locks")]
    scheduler --> postgres
    publisher --> postgres
    publisher -->|события| rabbitmq["RabbitMQ"]
    rabbitmq --> cnotif
    rabbitmq --> caudit
    cnotif --> postgres
    cnotif -.->|dev| mailpit["Mailpit"]
    caudit --> mongodb[("MongoDB: audit")]
    backend -.->|dev| mailpit
```

## События и очереди

**Что показывает:** полный путь доменного события. Действие в своей транзакции пишет строку в
`outbox_messages` (5a); publisher вычитывает готовые строки и публикует в topic-exchange `docsign.events`
с publisher confirms (5b); два consumer'а читают свои очереди (5c) — уведомления шлют письма, audit пишет в
MongoDB, дедуп по `event_id` (inbox). Отвергнутые (nack) уходят в dead-letter на ручной разбор. Пунктир —
путь ошибки.

```mermaid
flowchart LR
    action["Domain Action (в транзакции)"] --> outbox[("outbox_messages")]
    outbox --> publisher["publisher (worker)"]
    publisher --> ex{{"exchange docsign.events (topic)"}}
    ex -->|document.*.v1| qn["queue: notifications"]
    ex -->|document.*.v1| qa["queue: audit"]
    qn --> cnotif["consumer-notifications"]
    qa --> caudit["consumer-audit"]
    cnotif --> mail["письма: приглашения, истечение"]
    caudit --> mongo[("MongoDB: audit_events")]
    qn -.->|nack| dlx{{"docsign.dlx"}}
    qa -.->|nack| dlx
    dlx -.-> dlq[("docsign.dlq")]
```
