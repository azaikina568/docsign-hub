# 0002 — Outbox publisher: at-least-once via a single worker

- Status: Accepted
- Date: 2026-06-24

## Context

The transactional outbox (ADR [0001](0001-transactional-outbox.md)) stores domain events. A publisher
must ship them to RabbitMQ reliably, without losing events when the broker or the worker fails, and
without an over-engineered exactly-once machinery for a demo-scale topic.

## Decision

A single worker process (`outbox:publish --daemon`, the `publisher` container) reads `pending` rows and
publishes them to the topic exchange `docsign.events` using **publisher confirms** — a row is marked
`published` only after the broker acks it. Failures increment `attempts` with exponential backoff
(`available_at` pushed forward); after `max_attempts` the row becomes `failed` — the **producer-side
dead-letter**, inspected and re-queued manually (reset to `pending`).

Delivery is **at-least-once**: a crash between a successful publish and the `published` write republishes
the event on restart. We deliberately accept duplicates rather than chase exactly-once — the AMQP
`message_id` carries the `event_id`, and consumers dedup on it (ADR 0001 / inbox, Stage 5c).

A single worker means no row-level locking is needed. The publisher contract (`EventPublisher`) keeps the
transport behind an interface — swapped for a fake in tests, so the publish/retry logic is covered
without a broker.

## Consequences

- No lost events; broker downtime only delays delivery (rows wait in `pending`).
- Duplicates are possible by design → consumers must be idempotent.
- Horizontal scaling (multiple workers) is **not** supported as-is: it would double-publish. The path is
  `SELECT … FOR UPDATE SKIP LOCKED` (or a claim/visibility-timeout state); noted in
  `plans/SCALING_PERFORMANCE.md`, out of scope now.
- Holding one broker connection per worker; tight AMQP timeouts so a hung broker fails fast into retry.
