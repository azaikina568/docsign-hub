<?php

namespace App\Domain\Messaging\Services;

use App\Domain\Messaging\Data\OutboxEvent;
use App\Domain\Messaging\Enums\OutboxStatus;
use App\Domain\Messaging\Models\OutboxMessage;

class OutboxWriter
{
    /**
     * Пишет доменное событие в outbox. Вызывать ВНУТРИ транзакции доменного действия —
     * тогда бизнес-данные и событие коммитятся атомарно: при откате действия событие не «протечёт»,
     * а publisher (отдельный шаг) гарантированно отправит уже зафиксированные события.
     */
    public function record(OutboxEvent $event): OutboxMessage
    {
        return OutboxMessage::create([
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'routing_key' => $event->routingKey,
            'aggregate_type' => $event->aggregateType,
            'aggregate_id' => $event->aggregateId,
            'payload' => $event->toEnvelope(),
            'status' => OutboxStatus::Pending,
            'available_at' => now(),
        ]);
    }
}
