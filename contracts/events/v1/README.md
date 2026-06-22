# DocSign Hub event contracts

This directory contains versioned event contracts for messages published to RabbitMQ exchange `docsign.events`.

Rules for future contracts:

- Keep payloads stable and explicit.
- Do not serialize full Eloquent models.
- Include `event_id`, `event_type`, `occurred_at`, `aggregate_id`, `actor_id`, `version` and `data`.
- Add new versions instead of silently changing existing event shapes.
