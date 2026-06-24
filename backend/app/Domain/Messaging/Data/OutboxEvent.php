<?php

namespace App\Domain\Messaging\Data;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Доменное событие на пути в outbox. Не сериализуем Eloquent-модели — только стабильный payload
 * по контракту (`contracts/events/v1`), чтобы consumer'ы (в т.ч. будущие сервисы) не зависели от схемы БД.
 */
class OutboxEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $routingKey,
        public readonly string $aggregateType,
        public readonly ?string $aggregateId,
        public readonly ?int $actorId,
        public readonly Carbon $occurredAt,
        public readonly int $version,
        public readonly array $data,
    ) {}

    /**
     * Тип события вида `document.signed`; routing key и тип агрегата выводятся из него (`document.signed.v1`, `document`).
     *
     * @param  array<string, mixed>  $data
     */
    public static function make(string $type, ?string $aggregateId, ?int $actorId, array $data = [], int $version = 1): self
    {
        return new self(
            eventId: (string) Str::orderedUuid(),
            eventType: $type,
            routingKey: "{$type}.v{$version}",
            aggregateType: strtok($type, '.') ?: $type,
            aggregateId: $aggregateId,
            actorId: $actorId,
            occurredAt: now(),
            version: $version,
            data: $data,
        );
    }

    /**
     * Конверт для хранения в outbox и публикации в брокер (минимальный, без PII/секретов).
     *
     * @return array<string, mixed>
     */
    public function toEnvelope(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'routing_key' => $this->routingKey,
            'version' => $this->version,
            'occurred_at' => $this->occurredAt->toISOString(),
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'actor_id' => $this->actorId,
            'data' => $this->data,
        ];
    }
}
