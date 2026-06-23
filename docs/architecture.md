# DocSign Hub - architecture notes

DocSign Hub starts as a modular Laravel monolith. The goal is to keep the codebase understandable while showing how the system can grow into event-driven integrations later.

## Runtime

- `nginx` serves the Laravel API through PHP-FPM.
- `backend` runs Laravel 12 on PHP 8.4 in Docker.
- `frontend` runs Vue 3 + TypeScript + Vite.
- `postgres` stores business data.
- `redis` is reserved for cache, rate limiting and short locks.
- `rabbitmq` is reserved for domain events through an outbox publisher.
- `mongodb` is reserved for audit events and flexible event payloads.
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

**Roles & access.** The document owner is the only actor who manages the document (CRUD, parties,
send, cancel) — enforced by `DocumentPolicy`. Parties are the people who sign or view: they are
identified by name + email (a signer does not need an account), and are optionally linked to a
registered `User` (`document_parties.user_id`) when their email matches one. A document can only be
sent if it has at least one signer.

**Signing tokens.** `send` issues one signing token per signer; only its SHA-256 hash is stored.
The plain token is never returned to the sender — it is delivered to each signer's own email
(Mailpit in dev) through a `SigningInvitationNotifier`. Tokens always get an expiry
(`docsign.signing_token_ttl_days`, or the document's own `expires_at`).

**Decoupling seam.** `SendDocumentAction` raises a domain event `DocumentSent` after commit; the
`SendSigningInvitations` listener delivers invitations via the `SigningInvitationNotifier` contract
(infra implementation: `MailSigningInvitationNotifier`). This is the seam where the RabbitMQ outbox
and audit writers will hook in (Этап 5) without touching the action — and the boundary along which
notifications/audit could later move into separate services.

## Authentication

The API uses Laravel Sanctum personal access tokens (Bearer). Public `auth/register` and
`auth/login` endpoints are rate limited per IP; they return a plain-text token once, and only
its hash is stored. Protected endpoints sit behind the `auth:sanctum` guard, and `auth/logout`
revokes the current token. All `api/*` responses, including errors, are rendered as JSON.

## API documentation

The OpenAPI document is generated from the code (routes, Form Requests, Resources) by Scramble and
served as an interactive page at `/docs/api`, with the raw spec at `/docs/api.json`. It is not
committed; it always reflects the current routes. Access is restricted to the `local` environment.

Status transitions (the `partially_signed → signed` part is implemented by the upcoming signing flow):

```text
draft -> pending -> partially_signed -> signed
                 -> cancelled
                 -> expired
```

## Events

The planned RabbitMQ exchange is `docsign.events`. Event contracts live under `contracts/events/v1` and should contain stable, versioned payload shapes instead of serialized Eloquent models.

Planned routing keys:

```text
document.created.v1
document.party_added.v1
document.sent.v1
document.signed.v1
document.completed.v1
```

## Towards services (future-proofing)

The monolith is organised as bounded contexts under `app/Domain/<Context>` (today: `Documents`,
`Users`). The rules that keep them splittable later:

- Cross-context side effects go through **domain events** + **contracts (interfaces)**, never direct
  calls into another context's internals. `SendDocumentAction → DocumentSent → SigningInvitationNotifier`
  is the first example; notifications and audit will attach the same way.
- Anything crossing a process boundary is described by a **versioned contract** in
  `contracts/events/v1` (payload shapes, routing keys with a `.vN` suffix), not by serialized models.
- Infrastructure (mail, queue, storage) lives in `app/Infrastructure` behind domain contracts, so an
  implementation can be swapped for a remote service without touching the domain.

Likely evolution: extract `contracts/` into a shared package consumed by every service; move
`notifications`/`audit` consumers out as independent services around RabbitMQ. Synchronous internal
calls (e.g. a Go signature-verification service) could expose **gRPC** generated from the same
contracts — async events stay the default, gRPC only where a typed low-latency request/response is
actually needed.
