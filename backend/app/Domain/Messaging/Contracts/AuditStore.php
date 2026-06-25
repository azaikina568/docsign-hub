<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\Data\InboundEvent;

/**
 * Хранилище неизменяемого audit-журнала доменных событий. За контрактом — MongoDB (append-only);
 * в тестах подменяется фейком, чтобы не тянуть брокер/Mongo.
 */
interface AuditStore
{
    /**
     * Пишет событие в audit. Идемпотентно по event_id: повторная запись того же события — no-op.
     */
    public function record(InboundEvent $event): void;
}
