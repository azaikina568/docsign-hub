<?php

namespace Tests\Support;

use App\Domain\Messaging\Contracts\AuditStore;
use App\Domain\Messaging\Data\InboundEvent;

/**
 * Тестовое audit-хранилище: вместо MongoDB копит записанные события в памяти.
 */
class FakeAuditStore implements AuditStore
{
    /** @var list<InboundEvent> */
    public array $records = [];

    public function record(InboundEvent $event): void
    {
        $this->records[] = $event;
    }
}
