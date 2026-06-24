# DocSign Hub — diagrams

Mermaid-диаграммы. Рендерятся прямо на GitHub, а локально открываются как страница приложения на
`/docs/diagrams` (этот же файл, отрисованный mermaid.js — по аналогии с `/docs/api`). Держим их в
синхроне с кодом: схема БД — с миграциями, state machine — с `DocumentStatus::allowedTransitions()`,
sequence — с `SendDocumentAction` и листенерами.

Каждый раздел: сначала «что показывает», затем сама диаграмма, при необходимости — «как читать».
Содержание: [ERD](#схема-данных-erd) · [статусы документа](#жизненный-цикл-документа-state-machine) ·
[отправка на подписание](#отправка-на-подписание-sequence) · [обновление токенов](#обновление-токенов-sequence) ·
[верификация email](#верификация-email-sequence) · [развёртывание](#развёртывание-контейнеры) ·
[события и очереди](#события-и-очереди-план-этап-5).

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
```

Уникальные ключи: `documents.ulid`, `document_parties(document_id, email)`, `signature_tokens.token_hash`,
`signatures.document_party_id`. Составной индекс `documents(owner_id, status, created_at)` под список владельца.
Таблицы `signatures`/поле `content_hash`/`signature_tokens.used_at` — задел под подписание (Этап 4).

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

**Что показывает:** что происходит по `POST /documents/{ulid}/send` — от запроса владельца до письма
подписанту. Голубая рамка — одна транзакция БД (всё внутри либо коммитится, либо откатывается вместе).

```mermaid
sequenceDiagram
    actor Owner
    participant API as SendDocumentController
    participant Act as SendDocumentAction
    participant DB as PostgreSQL
    participant Ev as DocumentSent (event)
    participant L as SendSigningInvitations
    participant N as SigningInvitationNotifier
    participant Mail as Mailpit / провайдер
    actor Signer

    Owner->>API: POST /documents/{ulid}/send (Bearer)
    API->>Act: execute(document, owner)
    Note over Act: проверки draft и минимум один signer
    rect rgb(238,246,255)
    Act->>DB: BEGIN
    Act->>DB: на каждого signer создаём signature_token (hash, TTL)
    Act->>DB: ставим expires_at, статус draft в pending, пишем history
    Act->>DB: COMMIT
    end
    Act-->>Ev: dispatch после commit
    Ev->>L: handle
    L->>N: notify документа и invitation на каждого signer
    N->>Mail: письмо с персональной ссылкой (plain-токен)
    Mail-->>Signer: приглашение подписать
    API-->>Owner: 200 без токенов в ответе
```

Plain-токен живёт только в памяти (`SigningInvitation`) и уходит подписанту письмом; отправителю не
возвращается. Рассылка — после commit, чтобы сбой доставки не откатывал отправку.

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

**Что показывает:** какие контейнеры есть и кто с кем общается. Сплошные стрелки — текущий рантайм,
пунктир — dev-почта и заготовки под Этап 5.

```mermaid
flowchart LR
    Client(["Клиент / Vue SPA"]) -->|HTTP| nginx
    nginx -->|FastCGI| backend["backend (php-fpm, Laravel 12)"]
    scheduler["scheduler (schedule:work)"]
    backend --> postgres[("PostgreSQL")]
    backend --> redis[("Redis: cache, rate-limit, locks")]
    scheduler --> postgres
    scheduler --> redis
    backend -.->|dev| mailpit["Mailpit"]
    backend -.->|stage 5| rabbitmq["RabbitMQ"]
    backend -.->|stage 5| mongodb[("MongoDB: audit")]
```

## События и очереди (план, Этап 5)

**Что показывает:** будущий путь доменного события (ещё не реализовано). Транзакционный outbox → publisher
→ RabbitMQ topic → consumers (уведомления и audit), с dead-letter очередью для разбора сбоев.

```mermaid
flowchart LR
    action["Domain Action (в транзакции)"] --> outbox[("outbox_messages")]
    outbox --> publisher["publisher (worker)"]
    publisher --> ex{{"exchange docsign.events (topic)"}}
    ex -->|document.*.v1| qn["queue: notifications"]
    ex -->|document.*.v1| qa["queue: audit"]
    qn --> dlx{{"docsign.dlx"}}
    qa --> dlx
    dlx --> dlq[("docsign.dlq")]
    qa --> mongo[("MongoDB")]
```
