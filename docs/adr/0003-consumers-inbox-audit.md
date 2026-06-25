# 0003 — Consumers: inbox idempotency, sequential invitations, Mongo audit

- Status: Accepted
- Date: 2026-06-25

## Context

The outbox publisher (ADR [0002](0002-outbox-publisher.md)) delivers domain events to RabbitMQ
at-least-once. Stage 5c adds the read side: turn those events into side effects — notification emails and
an audit trail — without duplicating effects on redelivery, and deliver signing invitations one at a time
(signing is strictly sequential, yet `send` previously emailed every signer at once).

## Decision

**Consumers behind a contract.** Each consumer implements `EventConsumer` (name + queue + `handle`).
A generic command (`messaging:consume {name}`) runs `RabbitMqConsumer` (manual ack, `prefetch=1`): decode
the envelope into an `InboundEvent`, claim it in `inbox_messages` via `InboxGuard`, dispatch, then ack.
On handler failure the claim is released and the message is nacked **without requeue** — it dead-letters to
`docsign.dlq` for manual replay instead of hot-looping. Two consumers run, one container each:
`notifications` and `audit`.

**Idempotency = inbox.** A unique `(consumer, event_id)` row makes redelivery a no-op per consumer.
Effects that are not naturally idempotent (sending an email) are thus sent once; the Mongo audit write is
additionally idempotent on its own (`_id = event_id`, upsert-`$setOnInsert`).

**Sequential invitations (OPEN_QUESTIONS Q3).** `send` records `document.sent` and emails no one. The
notifications consumer invites the current-turn signer on `document.sent` (the first) and on every
`document.signed` while the document is still open (the next). Because the plain token is never stored, the
token is **re-minted at delivery** (`DeliverSigningInvitationAction`): a fresh random plain → new hash,
same deadline. The emailed link is therefore always one-time and current, and the database keeps only a
hash. Expiry notice rides the same consumer: on `document.expired` the owner is emailed.

**Audit in MongoDB.** `AuditConsumer` writes every event to an append-only `audit_events` collection
behind the `AuditStore` contract (`MongoAuditStore`); tests bind a fake. The raw envelope is stored as-is,
so the audit log preserves exactly what was delivered.

## Consequences

- Notifications no longer block the `send` request; delivery survives broker/worker restarts.
- Redelivery is safe (inbox); a poison message dead-letters instead of blocking the queue.
- Invitation delivery is idempotent per signer (`document_parties.invited_at`): the current-turn signer is
  emailed exactly once even if several events (sent + signed) or a redelivery point at them — important
  because re-minting would otherwise invalidate a previously delivered link.
- Re-minting on delivery keeps "only a hash in the DB" while allowing deferred delivery — at the cost of
  the send-time tokens for not-yet-invited signers being placeholders until their turn.
- Consumers **do** scale horizontally: competing consumers on a queue is the native RabbitMQ pattern, and
  the inbox makes redelivery a no-op, so adding instances is safe (unlike the single-instance publisher,
  which reads a DB table and would need `SKIP LOCKED`).
- `inbox_messages` and published `outbox_messages` grow unbounded; a scheduled `messaging:prune` trims rows
  past a retention window (claims older than the window can't be redelivered by the broker).
- Known limitation (out of scope, demo): the claim is taken before the side effect, so a crash mid-send
  loses that one invitation without auto-retry; a notification-outbox or a resend endpoint would close it.
  Any handler error dead-letters (no transient/poison classification) — production-grade DLQ is excluded.
- A second datastore (MongoDB) is now on the runtime path for audit; the `mongodb` PHP extension is added
  to the image.
