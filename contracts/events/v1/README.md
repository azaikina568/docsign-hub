# DocSign Hub event contracts (v1)

Versioned event contracts for messages published to the RabbitMQ exchange `docsign.events`.
Each domain action records an event into the transactional `outbox_messages` table in the same
transaction; a publisher (separate step) ships the stored envelope to the broker.

## Envelope

Every event shares the same envelope (see the `*.example.json` files):

| Field | Type | Notes |
| --- | --- | --- |
| `event_id` | uuid | Unique. Dedup key for at-least-once delivery. |
| `event_type` | string | e.g. `document.signed`. |
| `routing_key` | string | `{event_type}.v{version}`, the broker routing key. |
| `version` | int | Payload schema version. New field → same version; breaking change → `v{n+1}`. |
| `occurred_at` | string | ISO-8601 timestamp. |
| `aggregate_type` | string | e.g. `document`. |
| `aggregate_id` | string\|null | Public id (document ULID). |
| `actor_id` | int\|null | User who caused it; `null` when the system did (e.g. expiration). |
| `data` | object | Event-specific, minimal payload. |

## Routing keys

`document.created.v1`, `document.sent.v1`, `document.signed.v1`, `document.completed.v1`,
`document.cancelled.v1`, `document.expired.v1`.

## Rules

- Payloads are stable and explicit — never serialize full Eloquent models.
- Keep `data` minimal (ids, counts, timestamps); a consumer re-reads details via API/DB when needed.
- **No secrets or PII**: signing tokens and participant emails never go into an event.
- Add new versions instead of silently changing an existing event shape.
