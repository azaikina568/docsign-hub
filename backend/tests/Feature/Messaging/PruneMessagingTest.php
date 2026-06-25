<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Data\OutboxEvent;
use App\Domain\Messaging\Enums\DomainEventType;
use App\Domain\Messaging\Enums\OutboxStatus;
use App\Domain\Messaging\Models\InboxMessage;
use App\Domain\Messaging\Models\OutboxMessage;
use App\Domain\Messaging\Services\OutboxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function outbox(OutboxStatus $status, $publishedAt): OutboxMessage
    {
        $message = app(OutboxWriter::class)->record(
            OutboxEvent::make(DomainEventType::DocumentCreated, (string) Str::ulid(), 1, ['title' => 'X'])
        );

        return tap($message)->update(['status' => $status, 'published_at' => $publishedAt]);
    }

    public function test_prune_removes_old_delivered_rows_but_keeps_recent_and_failed(): void
    {
        $retention = (int) config('messaging.retention_days');

        $oldPublished = $this->outbox(OutboxStatus::Published, now()->subDays($retention + 1));
        $recentPublished = $this->outbox(OutboxStatus::Published, now()->subDay());
        $oldFailed = $this->outbox(OutboxStatus::Failed, now()->subDays($retention + 1));

        $oldInbox = InboxMessage::create(['consumer' => 'audit', 'event_id' => (string) Str::orderedUuid(), 'processed_at' => now()->subDays($retention + 1)]);
        $recentInbox = InboxMessage::create(['consumer' => 'audit', 'event_id' => (string) Str::orderedUuid(), 'processed_at' => now()->subDay()]);

        $this->artisan('messaging:prune')->assertSuccessful();

        // Удаляются только доставленные и старые служебные строки.
        $this->assertDatabaseMissing('outbox_messages', ['id' => $oldPublished->id]);
        $this->assertDatabaseMissing('inbox_messages', ['id' => $oldInbox->id]);
        // Свежие — остаются; failed (на ручной разбор) не трогаем.
        $this->assertDatabaseHas('outbox_messages', ['id' => $recentPublished->id]);
        $this->assertDatabaseHas('outbox_messages', ['id' => $oldFailed->id]);
        $this->assertDatabaseHas('inbox_messages', ['id' => $recentInbox->id]);
    }
}
