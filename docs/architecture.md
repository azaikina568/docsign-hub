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

The future signing flow will use status transitions:

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
