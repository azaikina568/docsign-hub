<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Actions\PublishPendingOutboxMessages;
use App\Domain\Messaging\Contracts\EventPublisher;
use App\Domain\Messaging\Data\OutboxEvent;
use App\Domain\Messaging\Enums\DomainEventType;
use App\Domain\Messaging\Enums\OutboxStatus;
use App\Domain\Messaging\Models\OutboxMessage;
use App\Domain\Messaging\Services\OutboxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Support\FakeEventPublisher;
use Tests\TestCase;

class PublishOutboxTest extends TestCase
{
    use RefreshDatabase;

    private FakeEventPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publisher = new FakeEventPublisher;
        $this->app->instance(EventPublisher::class, $this->publisher);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingMessage(array $overrides = []): OutboxMessage
    {
        $event = OutboxEvent::make(DomainEventType::DocumentCreated, (string) Str::ulid(), 1, [
            'title' => 'Agreement',
            'status' => 'draft',
        ]);

        $message = app(OutboxWriter::class)->record($event);

        return $overrides === [] ? $message : tap($message)->update($overrides);
    }

    private function publish(int $limit = 100): int
    {
        return app(PublishPendingOutboxMessages::class)->execute($limit);
    }

    /**
     * Прогон, толерантный к проброшенному исключению (сбой брокера прерывает батч).
     */
    private function publishIgnoringFailure(int $limit = 100): void
    {
        try {
            $this->publish($limit);
        } catch (\Throwable) {
            // ожидаемо: при сбое издателя action пробрасывает исключение
        }
    }

    public function test_publishes_pending_messages_and_marks_them_published(): void
    {
        $a = $this->pendingMessage();
        $b = $this->pendingMessage();

        $processed = $this->publish();

        $this->assertSame(2, $processed);
        $this->assertCount(2, $this->publisher->published);
        // message_id издателя = event_id (для дедупа на consumer'е).
        $this->assertSame($a->event_id, $this->publisher->published[0]['messageId']);

        foreach ([$a, $b] as $message) {
            $message->refresh();
            $this->assertSame(OutboxStatus::Published, $message->status);
            $this->assertNotNull($message->published_at);
        }
    }

    public function test_skips_published_and_not_yet_available(): void
    {
        $ready = $this->pendingMessage();
        $this->pendingMessage(['status' => OutboxStatus::Published, 'published_at' => now()]);
        $this->pendingMessage(['available_at' => now()->addHour()]);

        $processed = $this->publish();

        $this->assertSame(1, $processed);
        $this->assertCount(1, $this->publisher->published);
        $this->assertSame($ready->event_id, $this->publisher->published[0]['messageId']);
    }

    public function test_failed_publish_reschedules_with_backoff_and_stays_pending(): void
    {
        $this->publisher->shouldFail = true;
        $message = $this->pendingMessage();

        $this->publishIgnoringFailure();

        $message->refresh();
        $this->assertSame(OutboxStatus::Pending, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->error);
        $this->assertTrue($message->available_at->isFuture());
        $this->assertEmpty($this->publisher->published);
    }

    public function test_exhausted_attempts_move_message_to_failed(): void
    {
        $this->publisher->shouldFail = true;
        $max = (int) config('messaging.publisher.max_attempts');
        $message = $this->pendingMessage(['attempts' => $max - 1]);

        $this->publishIgnoringFailure();

        $message->refresh();
        $this->assertSame(OutboxStatus::Failed, $message->status);
        $this->assertSame($max, $message->attempts);
    }

    public function test_command_drains_all_pending_messages(): void
    {
        $this->pendingMessage();
        $this->pendingMessage();
        $this->pendingMessage();

        Artisan::call('outbox:publish');

        $this->assertSame(0, OutboxMessage::where('status', OutboxStatus::Pending)->count());
        $this->assertCount(3, $this->publisher->published);
    }
}
