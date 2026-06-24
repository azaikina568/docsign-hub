<?php

namespace App\Domain\Messaging\Data;

use App\Domain\Messaging\Enums\DomainEventType;
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
     * Собирает событие по типу из реестра `DomainEventType` (он же задаёт routing key и версию).
     *
     * @param  array<string, mixed>  $data
     */
    public static function make(DomainEventType $type, ?string $aggregateId, ?int $actorId, array $data = []): self
    {
        return new self(
            eventId: (string) Str::orderedUuid(),
            eventType: $type->value,
            routingKey: $type->routingKey(),
            aggregateType: $type->aggregateType(),
            aggregateId: $aggregateId,
            actorId: $actorId,
            occurredAt: now(),
            version: $type->version(),
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
