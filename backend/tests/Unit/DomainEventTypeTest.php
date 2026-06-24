<?php

namespace Tests\Unit;

use App\Domain\Messaging\Enums\DomainEventType;
use PHPUnit\Framework\TestCase;

class DomainEventTypeTest extends TestCase
{
    public function test_routing_key_appends_version(): void
    {
        $this->assertSame('document.signed.v1', DomainEventType::DocumentSigned->routingKey());
        $this->assertSame('document.created.v1', DomainEventType::DocumentCreated->routingKey());
    }

    public function test_aggregate_type_is_event_prefix(): void
    {
        foreach (DomainEventType::cases() as $type) {
            $this->assertSame('document', $type->aggregateType());
            $this->assertSame(1, $type->version());
        }
    }

    public function test_registry_lists_the_full_document_lifecycle(): void
    {
        $values = array_map(fn (DomainEventType $c) => $c->value, DomainEventType::cases());

        $this->assertSame([
            'document.created',
            'document.sent',
            'document.signed',
            'document.completed',
            'document.cancelled',
            'document.expired',
        ], $values);
    }
}
