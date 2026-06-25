<?php

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Services\InboxGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InboxGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_claims_event_once_then_treats_as_duplicate(): void
    {
        $guard = app(InboxGuard::class);
        $eventId = (string) Str::orderedUuid();

        $this->assertTrue($guard->claim('audit', $eventId));
        // Повторная доставка того же события тому же consumer'у — дубль.
        $this->assertFalse($guard->claim('audit', $eventId));
    }

    public function test_same_event_can_be_claimed_by_different_consumers(): void
    {
        $guard = app(InboxGuard::class);
        $eventId = (string) Str::orderedUuid();

        // notifications и audit обрабатывают одно событие независимо.
        $this->assertTrue($guard->claim('notifications', $eventId));
        $this->assertTrue($guard->claim('audit', $eventId));
    }

    public function test_release_allows_reprocessing(): void
    {
        $guard = app(InboxGuard::class);
        $eventId = (string) Str::orderedUuid();

        $this->assertTrue($guard->claim('notifications', $eventId));
        // Обработчик упал — снимаем заявку, чтобы ручной реплей смог переобработать.
        $guard->release('notifications', $eventId);
        $this->assertTrue($guard->claim('notifications', $eventId));
    }
}
