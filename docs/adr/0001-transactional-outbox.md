# 0001 — Domain events via a transactional outbox

- Status: Accepted
- Date: 2026-06-24

## Context

Domain actions (send, sign, complete, cancel, expire) must publish events to RabbitMQ so other
concerns — notifications, audit, and future services — can react. Publishing straight from an action
is unsafe: if the broker is down or the transaction rolls back after a publish, business state and
emitted events diverge (lost or phantom events). We also want the core to stay splittable into
services later without rewrites.

## Decision

Use the **transactional outbox** pattern. Each domain action writes an `outbox_messages` row **inside
the same database transaction** as its business changes (`OutboxWriter::record(OutboxEvent)`), so data
and event commit or roll back together. A separate publisher (next step) reads pending rows and ships
them to the `docsign.events` exchange, marking them published — decoupling delivery from the request.

Events carry a stable, versioned envelope (`contracts/events/v1`), never serialized Eloquent models,
with minimal `data` and **no secrets or PII** (signing tokens and emails stay out of events).

## Consequences

- Atomicity: no lost or phantom events; broker downtime never blocks a domain action.
- At-least-once delivery downstream → consumers must be idempotent (inbox/dedup by `event_id`).
- Slight write amplification (one extra row per event) and a publisher process to operate.
- Clean seam toward microservices: notifications/audit become consumers around the same contracts.
