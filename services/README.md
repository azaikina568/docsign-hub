# Polyglot services

Self-contained services that integrate with the core via the shared event contracts (`../contracts/`),
not by cutting the core apart. Each is a new consumer/producer of the same RabbitMQ contracts (or a
read-model over existing data). Monorepo by design — one place shows the whole system.

| Service | Language / framework | Role | Status |
| --- | --- | --- | --- |
| `signing-worker` | Go + Fiber | Verify signatures: consume `document.signed.v1` → recompute & check `content_hash`/`signature_hash` (shared Postgres, read-only) → publish `signature.verified.v1`. Also a small REST surface (`/healthz`, `/metrics`, sync `POST /verify`). | planned (GO-1+) |
| `analytics` | Python + FastAPI | Analytical REST read-model over the audit log (Mongo) + Postgres: time-to-sign, send→sign conversion, expiration rate, throughput. Auto-OpenAPI. Internal access only. | planned (PY-1+) |

Design, integration and the step order — `../plans/POLYGLOT_SERVICES.md` (local). Events the services produce
that are not emitted by the PHP outbox are marked `x-producer: <service>` in `../contracts/asyncapi.json`.
