<?php

namespace App\Infrastructure\Messaging;

use App\Domain\Messaging\Contracts\AuditStore;
use App\Domain\Messaging\Data\InboundEvent;
use MongoDB\Client;
use MongoDB\Collection;

/**
 * Audit-журнал в MongoDB: append-only коллекция `audit_events`. Пишем сырой конверт события целиком —
 * аудит должен сохранять то, что реально пришло. Идемпотентность — `_id = event_id` + upsert-`$setOnInsert`:
 * повторная доставка не перезаписывает уже записанное событие.
 */
class MongoAuditStore implements AuditStore
{
    private ?Client $client = null;

    /**
     * @param  array<string, mixed>  $config  config('messaging.audit')
     */
    public function __construct(private readonly array $config) {}

    public function record(InboundEvent $event): void
    {
        $this->collection()->updateOne(
            ['_id' => $event->eventId],
            ['$setOnInsert' => array_merge($event->envelope, ['recorded_at' => now()->toISOString()])],
            ['upsert' => true],
        );
    }

    private function collection(): Collection
    {
        $this->client ??= new Client((string) $this->config['uri']);

        return $this->client->getCollection(
            (string) $this->config['database'],
            (string) $this->config['collection'],
        );
    }
}
