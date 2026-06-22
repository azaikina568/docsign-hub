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
which updates the document and appends a `document_status_history` row inside the same transaction.
Access is owner-only via `DocumentPolicy`. Parties can only change while the document is a draft;
`send` issues a signing token per party — the plain token is returned once and only its SHA-256
hash is stored, ready for the public signing flow.

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
