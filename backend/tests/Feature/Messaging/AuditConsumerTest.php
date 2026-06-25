<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Consumers\AuditConsumer;
use App\Domain\Messaging\Contracts\AuditStore;
use App\Domain\Messaging\Data\InboundEvent;
use App\Domain\Messaging\Enums\DomainEventType;
use App\Infrastructure\Messaging\MongoAuditStore;
use Illuminate\Support\Str;
use Tests\Support\FakeAuditStore;
use Tests\TestCase;

class AuditConsumerTest extends TestCase
{
    private function inboundEvent(DomainEventType $type): InboundEvent
    {
        return InboundEvent::fromEnvelope([
            'event_id' => (string) Str::orderedUuid(),
            'event_type' => $type->value,
            'routing_key' => $type->routingKey(),
            'version' => 1,
            'occurred_at' => now()->toISOString(),
            'aggregate_type' => 'document',
            'aggregate_id' => (string) Str::ulid(),
            'actor_id' => 7,
            'data' => ['foo' => 'bar'],
        ]);
    }

    public function test_records_event_in_audit_store(): void
    {
        $store = new FakeAuditStore;
        $consumer = new AuditConsumer($store);

        $event = $this->inboundEvent(DomainEventType::DocumentSigned);
        $consumer->handle($event);

        $this->assertCount(1, $store->records);
        $this->assertSame($event->eventId, $store->records[0]->eventId);
        $this->assertSame('document.signed', $store->records[0]->eventType);
    }

    public function test_records_every_event_type(): void
    {
        $store = new FakeAuditStore;
        $consumer = new AuditConsumer($store);

        foreach (DomainEventType::cases() as $type) {
            $consumer->handle($this->inboundEvent($type));
        }

        $this->assertCount(count(DomainEventType::cases()), $store->records);
    }

    public function test_consumer_metadata(): void
    {
        $consumer = new AuditConsumer(new FakeAuditStore);

        $this->assertSame('audit', $consumer->name());
        $this->assertSame('docsign.audit', $consumer->queue());
    }

    public function test_container_binds_mongo_audit_store(): void
    {
        // По умолчанию (вне тестового override) контракт указывает на Mongo-реализацию.
        $this->assertInstanceOf(
            MongoAuditStore::class,
            $this->app->make(AuditStore::class),
        );
    }
}
