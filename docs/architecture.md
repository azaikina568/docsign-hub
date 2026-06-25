# DocSign Hub - architecture notes

DocSign Hub starts as a modular Laravel monolith. The goal is to keep the codebase understandable while showing how the system can grow into event-driven integrations later.

## Runtime

Diagrams (ERD, state machine, send sequence, deployment, messaging): [diagrams.md](diagrams.md) —
rendered on GitHub and served in-app at `/docs/diagrams` (same file via mermaid.js).

- `nginx` serves the Laravel API through PHP-FPM.
- `backend` runs Laravel 12 on PHP 8.4 in Docker.
- `scheduler` is a dedicated container running `php artisan schedule:work` (drives `documents:expire`).
- `publisher` reads the outbox and publishes domain events (`outbox:publish --daemon`).
- `consumer-notifications` / `consumer-audit` read RabbitMQ and deliver emails / write the audit log.
- `frontend` runs Vue 3 + TypeScript + Vite.
- `postgres` stores business data.
- `redis` is reserved for cache, rate limiting and short locks.
- `rabbitmq` carries domain events (topic exchange `docsign.events`) from the outbox publisher.
- `mongodb` stores the append-only audit log (`audit_events`).
- `mailpit` is used for local demo notifications.

## Backend Direction

Controllers should stay thin: request validation, authorization, action/service call, resource response. Domain logic belongs to `app/Domain`, API controllers to `app/Http/Controllers/Api/V1`, and infrastructure concerns to `app/Infrastructure`.

## Documents domain

The documents feature lives under `app/Domain/Documents` (Enums, Models, Actions, Services,
Policies). Tables: `documents`, `document_parties`, `signature_tokens`, `signatures`,
`document_status_history`. Status is a PHP enum (`DocumentStatus`) stored as a string column.

Each lifecycle action is a single-purpose class (`CreateDocumentAction`, `AddDocumentPartyAction`,
`SendDocumentAction`, `CancelDocumentAction`, …). Status changes go through `DocumentStatusService`,
which validates the move against an explicit transition map (`DocumentStatus::allowedTransitions()`),
updates the document and appends a `document_status_history` row inside the same transaction —
so a status can never be "skipped".

**Public identifiers.** A document is addressed in the API and URLs by a `ulid` (unique, generated on
create), not by its auto-increment primary key. The integer PK stays internal for foreign keys; the
ULID is what `DocumentResource` exposes as `id` and what route-model binding resolves
(`Document::getRouteKeyName()`). Nested parties keep their integer ids (scoped to the document).

**Expiration.** `ExpireDocumentsAction` (driven by the `documents:expire` console command, scheduled
daily in `routes/console.php` and run by the `scheduler` container) moves any `pending`/`partially_signed`
document past its `expires_at` to `expired` through the same transition map and history (actor = system,
so `changed_by_user_id` is null). It streams candidates with `lazyById()` (constant memory) and expires
each document in its own transaction, so one failure does not roll back the rest. The scheduled task is
marked `onOneServer()` so duplicate schedulers cannot run it twice.

A document's deadline is set at `send`: if the owner gave no `expires_at`, the default TTL
(`docsign.signing_token_ttl_days`) is persisted onto the document. So a sent document always has a
deadline and its signing tokens share the exact same instant — there is no "sent but never expiring"
state.

**Roles & access.** Two distinct concepts. The **owner** manages the document (CRUD, parties, send,
cancel) — enforced by `DocumentPolicy`. **Parties** are the people involved per document, with a
`role`: a `signer` signs (and has a `signing_order` for sequential signing), a `viewer` only reads
(`signing_order` is null — it is meaningless for viewers). Parties are identified by name + email (a
signer does not need an account) and are optionally linked to a registered `User`
(`document_parties.user_id`) when their email matches one. A document can only be sent if it has at
least one signer.

Access uses one consistent model — **identity or capability**. Identity = an authenticated `User`
(Sanctum), used for owner actions and for parties that are linked to an account. Capability = a
single-use signing token delivered to a party's email, used by parties without an account. The
signing flow combines them: an account-bound party (`document_parties.user_id` set) must be
authenticated as that user (a leaked token alone is not enough), while an email-only party signs by
possession of the token. See **Signing** below.

**content_hash.** Set at `send` to a SHA-256 of the signable snapshot (today: title + ordered
parties; later: the uploaded file). Each signature binds to it (`signature_hash` derives from it), so
a later content change is detectable against existing signatures. It is an **internal** integrity
artifact — not exposed in the document API (no client use, and raw internal hashes are needless
information disclosure); a future signature-verification endpoint would surface it with context.

**Signing tokens.** `send` issues one signing token per signer; only its SHA-256 hash is stored.
The plain token is never returned to the sender. Every token expires at the document's deadline
(see Expiration above), so a token is never open-ended.

**Sequential invitation delivery.** Signing is strictly sequential, so invitations are delivered the
same way — one at a time, not all at once (OPEN_QUESTIONS Q3). `send` no longer emails anyone
synchronously; it records `document.sent` and returns. The notifications consumer drives delivery:
on `document.sent` it invites the first signer, on each `document.signed` (while the document is not
complete) the next. `SigningInvitationCoordinator` finds the current-turn signer; `DeliverSigningInvitationAction`
**re-mints** that signer's token at delivery time (fresh random plain → new hash, same deadline) and
sends it via the `SigningInvitationNotifier` contract (`MailSigningInvitationNotifier`). Re-minting is
why the plain token need not be stored: the link is generated when the email is sent, so the database
still keeps only a hash. Delivery is **idempotent per signer** via `document_parties.invited_at` (set
only after a successful send): a signer is emailed once even when several events point at them — e.g. an
account-bound signer signs from the dashboard before `document.sent` is processed, so both `sent` and the
resulting `signed` would otherwise resolve to the same next signer. Without that guard a second delivery
would re-mint and invalidate the link already in the signer's inbox. This is also the boundary along which
notification delivery could move into a separate service — the action and contract stay put.

**Signing.** `SignDocumentAction` is the single entry for both scenarios. Public capability path:
`GET /signing/{token}` returns a party's own context (no other parties' data — only aggregate
progress) and `POST /signing/{token}/sign` signs. Identity path for registered parties:
`GET /signing-requests` lists pending participations and `POST /signing-requests/{party}/sign` signs
under `auth:sanctum`. Rules enforced inside the action, in this order: the actor is authorised first
(capability token for email-only parties, matching login for account-bound — so a non-participant
always gets 403 and cannot probe document state); signing is **idempotent** (re-signing returns the
existing signature); the document must be open and not past its deadline; the token must be unused;
and **strictly sequential** — a signer with `signing_order = N` is blocked until every signer with a
smaller order has signed. The document row is locked (`lockForUpdate`) for the duration to serialise
concurrent signatures. Each signature stores `signature_hash` (`sha256(content_hash + party_id +
signed_at + nonce)`), a `signed_payload` snapshot, ip and user-agent; the token is marked `used_at`.
The document advances `pending → partially_signed` and, once all signers are done, `→ signed`.

**Deadline extension.** `PATCH /documents/{ulid}/deadline` (owner, document still awaiting
signatures) moves `expires_at` forward and updates the still-unused tokens to the same instant,
keeping the "document deadline = token deadline" invariant. It records an audit row in the status
history without a status change. Terminal documents cannot be extended, and the deadline only moves
forward.

## Authentication

The API uses Laravel Sanctum personal access tokens (Bearer). Public `auth/register` and
`auth/login` endpoints are rate limited per IP (`throttle:auth`, 10/min); only token hashes are
stored. Protected endpoints sit behind the `auth:sanctum` guard and a general `throttle:api` limiter
(60/min, keyed by user id, falling back to IP). All `api/*` responses, including errors, are JSON.

**Access + refresh tokens.** `register`/`login` issue a pair: a short-lived **access** token
(ability `access-api`, TTL `sanctum.access_token_expiration`) for the API, and a long-lived
**refresh** token (ability `issue-access`, TTL `sanctum.refresh_token_expiration`) used only at
`POST /auth/refresh`. The abilities make them non-interchangeable: a refresh token is rejected by the
API group (`abilities:access-api`), and an access token is rejected by the refresh route
(`abilities:issue-access`). Per-token TTLs are set explicitly (not via Sanctum's global `expiration`,
which would apply one TTL to all tokens).

A pair shares a `family` (uuid column on `personal_access_tokens`, custom `PersonalAccessToken`
model). `RefreshTokensAction` rotates the refresh: it marks the presented one `consumed_at`, deletes
the family's old access, and issues a new pair in the same family. **Reuse detection:** presenting an
already-consumed refresh means it was likely stolen — the whole family is revoked and the request
gets 401. `logout` also deletes the whole family (access + refresh). Expired/rotated tokens are
pruned daily by the scheduled `sanctum:prune-expired`.

**Email verification.** `User` implements `MustVerifyEmail`. Registration dispatches `Registered`, so
the framework mails a signed verification link (Mailpit in dev). The verify route
(`GET /auth/verify/{id}/{hash}`, name `verification.verify`) is **public**: the URL signature is itself
the capability (proof of mailbox control), so no Bearer token is needed — the `signed` middleware
validates the signature and expiry, and the controller re-checks the hash against the user's current
email. It is idempotent (re-visiting an already-verified link returns 200). `POST /auth/verification/resend`
(authenticated) re-sends the link; both verification routes share a tight `throttle:verification` limiter.
The **gate** lives at the document-creation boundary: `DocumentPolicy::create` requires a verified email.
Because verification is monotonic (no email-change or un-verify path exists), gating creation transitively
covers every later owner action (send/cancel/parties/deadline) — they only exist on documents created by
an already-verified owner. `DocumentPolicy::create` is also the extension point for a future
who-may-create policy (invite-only / quota / subscription — see `plans/OPEN_QUESTIONS.md` Q2).

## API documentation

The OpenAPI document is generated from the code (routes, Form Requests, Resources) by Scramble and
served as an interactive page at `/docs/api`, with the raw spec at `/docs/api.json`. It is not
committed; it always reflects the current routes. Access is restricted to the `local` environment.

Status transitions:

```text
draft -> pending -> partially_signed -> signed
                 -> cancelled
                 -> expired
```

## Domain events & outbox

Domain actions emit events through a **transactional outbox** (ADR
[0001](adr/0001-transactional-outbox.md)). Inside the same `DB::transaction` as its business writes,
each action calls `OutboxWriter::record(OutboxEvent)` (`app/Domain/Messaging`), inserting a row into
`outbox_messages` — so business state and the emitted event commit or roll back together (no lost or
phantom events). Events recorded today: `document.created` (create), `document.sent` (send),
`document.signed` (per signature), `document.completed` (envelope fully signed), `document.cancelled`
(cancel), `document.expired` (expiration command).

Event types live in one registry — the `DomainEventType` enum (single source of truth for type,
version and routing key), so producers and consumers never drift on magic strings. Each event is a
stable, versioned envelope (`event_id`, `event_type`, `routing_key` `*.vN`, `occurred_at`,
`aggregate_id`, `actor_id`, `data`) — never a serialized Eloquent model, and with **no secrets or
PII** (signing tokens and participant emails stay out of events). Each event shape is pinned by a
JSON Schema in `contracts/events/v1/*.schema.json`; a test validates every emitted payload against its
schema, so the contract is enforced, not just illustrated (an AsyncAPI catalog is still planned — see
`plans/MESSAGING_DESIGN.md`). `outbox_messages` has no foreign key to documents (it references the
document ULID and carries a self-contained payload), so the messaging concern is not coupled to the
domain schema and can move to a separate service later.

**Publisher.** A worker (`outbox:publish --daemon`, the `publisher` container) reads `pending` rows
(`status, available_at` index) and publishes each to the topic exchange `docsign.events` via
`EventPublisher` → `RabbitMqEventPublisher` (php-amqplib, publisher confirms). On the broker's ack the
row is marked `published`; on failure `attempts` is incremented with exponential backoff
(`available_at` pushed forward), and after `max_attempts` the row becomes `failed` — the producer-side
dead-letter for manual inspection. The AMQP `message_id` carries the `event_id` so consumers can dedup
(delivery is at-least-once). `messaging:setup` declares the topology idempotently (the topic exchange,
a `docsign.dlx`/`docsign.dlq` dead-letter pair, and the durable `docsign.notifications`/`docsign.audit`
queues bound to `document.*.v1`).

**Consumers.** Two long-running workers (`messaging:consume {name}`, one container each) read their
queue with manual ack and `prefetch=1`. `RabbitMqConsumer` decodes the envelope into an `InboundEvent`,
the command wraps each message with `InboxGuard` (a `(consumer, event_id)` claim in `inbox_messages`)
for idempotency, then dispatches to an `EventConsumer`. On success the message is acked; on failure the
inbox claim is released and the message is nacked without requeue, so it dead-letters to `docsign.dlq`
for manual replay rather than hot-looping. The two consumers:

- **notifications** (`NotificationsConsumer`) → drives sequential invitation delivery (see above) and,
  on `document.expired`, emails the owner that the document lapsed.
- **audit** (`AuditConsumer`) → appends every event to an immutable MongoDB collection (`audit_events`)
  via the `AuditStore` contract (`MongoAuditStore`). The Mongo `_id` is the `event_id` with an
  upsert-`$setOnInsert`, so a redelivered event never overwrites what was recorded.

Consumers scale horizontally — competing consumers on a queue is RabbitMQ's native pattern and the inbox
makes redelivery a no-op (unlike the single-instance publisher, which reads a DB table). `inbox_messages`
and published `outbox_messages` are trimmed past a retention window by a scheduled `messaging:prune` so the
bookkeeping tables don't grow forever; `failed` outbox rows and the audit log are kept.

Routing keys:

```text
document.created.v1
document.sent.v1
document.signed.v1
document.completed.v1
document.cancelled.v1
document.expired.v1
```

## Towards services (future-proofing)

The monolith is organised as bounded contexts under `app/Domain/<Context>` (today: `Documents`,
`Users`). The rules that keep them splittable later:

- Cross-context side effects go through **domain events** + **contracts (interfaces)**, never direct
  calls into another context's internals. The notifications and audit consumers attach to the same
  versioned events behind `EventConsumer`/`AuditStore`/`SigningInvitationNotifier` contracts.
- Anything crossing a process boundary is described by a **versioned contract** in
  `contracts/events/v1` (payload shapes, routing keys with a `.vN` suffix), not by serialized models.
- Infrastructure (mail, queue, storage) lives in `app/Infrastructure` behind domain contracts, so an
  implementation can be swapped for a remote service without touching the domain.

Likely evolution: extract `contracts/` into a shared package consumed by every service; move
`notifications`/`audit` consumers out as independent services around RabbitMQ. Synchronous internal
calls (e.g. a Go signature-verification service) could expose **gRPC** generated from the same
contracts — async events stay the default, gRPC only where a typed low-latency request/response is
actually needed.
