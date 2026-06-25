<?php

use App\Domain\Documents\Consumers\NotificationsConsumer;
use App\Domain\Messaging\Consumers\AuditConsumer;

return [
    /*
     * Подключение к RabbitMQ. Таймауты заданы намеренно — чтобы воркер-публишер не висел
     * бесконечно на недоступном брокере, а быстро упал в ретрай.
     */
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'docsign'),
        'password' => env('RABBITMQ_PASSWORD', 'docsign'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'docsign.events'),
        'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
        'read_write_timeout' => (float) env('RABBITMQ_RW_TIMEOUT', 6.0),
        // 0 = без heartbeat: publish-only воркер во время idle-сна не обслуживает heartbeat'ы,
        // иначе брокер рвёт простаивающее соединение; живость и так покрыта reconnect-on-error.
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 0),
    ],

    /*
     * Каталог JSON Schema контрактов событий (`contracts/events/v1`). Лежит в корне репозитория;
     * в Docker проброшен в /srv/contracts (см. docker-compose), локально берём его же по дефолту.
     */
    'contracts_path' => env('DOCSIGN_CONTRACTS_PATH', base_path('../contracts/events/v1')),

    /*
     * Топология брокера (объявляется идемпотентно командой messaging:setup).
     * Очереди уже забиндены под Этап 5c (consumers), сейчас просто копят сообщения как доказательство доставки.
     */
    'topology' => [
        'dead_letter_exchange' => 'docsign.dlx',
        'dead_letter_queue' => 'docsign.dlq',
        'queues' => [
            'docsign.notifications' => 'document.*.v1',
            'docsign.audit' => 'document.*.v1',
        ],
    ],

    /*
     * Публишер outbox → брокер: размер батча, лимит попыток (после него строка уходит в failed —
     * это «dead-letter» на стороне продюсера, разбираем вручную) и экспоненциальный backoff.
     */
    'publisher' => [
        'batch' => (int) env('OUTBOX_PUBLISH_BATCH', 100),
        'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 8),
        'backoff_base_seconds' => (int) env('OUTBOX_BACKOFF_BASE', 10),
        'backoff_max_seconds' => (int) env('OUTBOX_BACKOFF_MAX', 300),
        'idle_sleep_seconds' => (int) env('OUTBOX_IDLE_SLEEP', 1),
    ],

    /*
     * Реестр consumer'ов: имя → класс (команда `messaging:consume {name}`). Каждый читает свою очередь
     * и дедуплицирует по event_id (inbox). Имя — ключ идемпотентности.
     */
    'consumers' => [
        'notifications' => NotificationsConsumer::class,
        'audit' => AuditConsumer::class,
    ],

    /*
     * Audit-журнал доменных событий в MongoDB (append-only). URI/база берутся из общего .env,
     * коллекция — неизменяемый поток событий.
     */
    'audit' => [
        'uri' => env('MONGODB_URI', 'mongodb://mongodb:27017'),
        'database' => env('MONGODB_DATABASE', 'docsign_hub'),
        'collection' => env('MONGODB_AUDIT_COLLECTION', 'audit_events'),
    ],

    /*
     * Срок хранения служебных строк обмена (дни): опубликованные outbox-строки и обработанные inbox-строки
     * старше окна чистит `messaging:prune` — иначе таблицы растут без предела. Окно с запасом больше любого
     * мыслимого времени переотправки брокером, так что повторная доставка после чистки невозможна.
     * audit-журнал (Mongo) и `failed`-строки outbox не трогаем — это намеренно долгоживущие данные.
     */
    'retention_days' => (int) env('MESSAGING_RETENTION_DAYS', 7),
];
