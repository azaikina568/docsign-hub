# DocSign Hub — diagrams

Mermaid-диаграммы. Рендерятся прямо на GitHub, а локально открываются как страница приложения на
`/docs/diagrams` (этот же файл, отрисованный mermaid.js — по аналогии с `/docs/api`). Держим их в
синхроне с кодом: схема БД — с миграциями, state machine — с `DocumentStatus::allowedTransitions()`,
sequence — с `SendDocumentAction` и листенерами.

## Схема данных (ERD)

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

Источник истины — `DocumentStatus::allowedTransitions()`; переходы пишутся в `document_status_history`.

```mermaid
stateDiagram-v2
    [*] --> draft : create
    draft --> pending : send (минимум 1 signer)
    draft --> cancelled : cancel
    pending --> partially_signed : sign (stage 4)
    pending --> signed : sign last (stage 4)
    pending --> cancelled : cancel
    pending --> expired : срок истёк
    partially_signed --> signed : sign last (stage 4)
    partially_signed --> cancelled : cancel
    partially_signed --> expired : срок истёк
    signed --> [*]
    cancelled --> [*]
    expired --> [*]
```

## Отправка на подписание (sequence)

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

## Развёртывание (контейнеры)

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

Сплошные стрелки — текущий рантайм; пунктир — dev-почта и заготовки под Этап 5.

## События и очереди (план, Этап 5)

Транзакционный outbox → publisher → RabbitMQ topic → consumers.

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
