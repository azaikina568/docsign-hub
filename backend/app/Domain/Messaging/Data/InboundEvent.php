<?php

namespace App\Domain\Messaging\Data;

/**
 * Доменное событие, прочитанное consumer'ом из брокера. Разбирает конверт (см. OutboxEvent::toEnvelope)
 * в типизированные поля; сырой конверт сохраняем для audit, чтобы писать событие как есть, без потерь.
 */
class InboundEvent
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $envelope
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $routingKey,
        public readonly ?string $aggregateType,
        public readonly ?string $aggregateId,
        public readonly ?int $actorId,
        public readonly ?string $occurredAt,
        public readonly int $version,
        public readonly array $data,
        public readonly array $envelope,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(array $envelope): self
    {
        return new self(
            eventId: (string) ($envelope['event_id'] ?? ''),
            eventType: (string) ($envelope['event_type'] ?? ''),
            routingKey: (string) ($envelope['routing_key'] ?? ''),
            aggregateType: isset($envelope['aggregate_type']) ? (string) $envelope['aggregate_type'] : null,
            aggregateId: isset($envelope['aggregate_id']) ? (string) $envelope['aggregate_id'] : null,
            actorId: isset($envelope['actor_id']) ? (int) $envelope['actor_id'] : null,
            occurredAt: isset($envelope['occurred_at']) ? (string) $envelope['occurred_at'] : null,
            version: (int) ($envelope['version'] ?? 1),
            data: is_array($envelope['data'] ?? null) ? $envelope['data'] : [],
            envelope: $envelope,
        );
    }
}
