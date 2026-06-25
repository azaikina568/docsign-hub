# Architecture Decision Records

Short records of significant architectural decisions (MADR-style: context → decision → consequences).
One file per decision, numbered, never rewritten — superseding decisions get a new record.

| # | Decision | Status |
| --- | --- | --- |
| [0001](0001-transactional-outbox.md) | Domain events via a transactional outbox | Accepted |
| [0002](0002-outbox-publisher.md) | Outbox publisher: at-least-once via a single worker | Accepted |
| [0003](0003-consumers-inbox-audit.md) | Consumers: inbox idempotency, sequential invitations, Mongo audit | Accepted |
