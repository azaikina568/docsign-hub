<?php

namespace App\Domain\Messaging\Consumers;

use App\Domain\Messaging\Contracts\AuditStore;
use App\Domain\Messaging\Contracts\EventConsumer;
use App\Domain\Messaging\Data\InboundEvent;

/**
 * Audit-consumer: пишет каждое доменное событие в неизменяемый журнал (MongoDB). Очередь забиндена
 * на `document.*.v1`, поэтому пишем всё подряд — фильтрация по типу здесь не нужна.
 */
class AuditConsumer implements EventConsumer
{
    public function __construct(private readonly AuditStore $store) {}

    public function name(): string
    {
        return 'audit';
    }

    public function queue(): string
    {
        return 'docsign.audit';
    }

    public function handle(InboundEvent $event): void
    {
        $this->store->record($event);
    }
}
