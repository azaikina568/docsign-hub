# signing-worker (Go + Fiber)

Сервис проверки подписи DocSign Hub. Полиглот-демо: PHP пишет событие → Go верифицирует → событие обратно в поток.

**Сейчас (GO-1):** только HTTP-каркас — `/healthz` и `/metrics` (Prometheus) + graceful shutdown по SIGTERM.
**Дальше (GO-2):** потребитель `document.signed.v1` из RabbitMQ → пересчёт `content_hash`/`signature_hash`
(чтение `signatures`/`documents` из общей Postgres, read-only) → публикация `signature.verified.v1` + inbox-дедуп.
Контракт события — `../../contracts/events/v1/signature.verified.schema.json`; общий дизайн — `../../plans/POLYGLOT_SERVICES.md`.

## Локально

```bash
go test ./...                 # юнит-тесты
go run .                      # сервис на :8090 (env SIGNING_WORKER_ADDR)
curl localhost:8090/healthz   # {"status":"ok"}
curl localhost:8090/metrics   # Prometheus
```

В Docker — сервис `signing-worker` в корневом `docker-compose.yml` (порт `${SIGNING_WORKER_PORT:-8090}`).

## Переменные окружения

| Переменная | Назначение | Default |
| --- | --- | --- |
| `SIGNING_WORKER_ADDR` | адрес HTTP-сервера | `:8090` |
