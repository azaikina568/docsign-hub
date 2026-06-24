<?php

namespace Tests\Support;

use App\Domain\Messaging\Contracts\EventPublisher;
use RuntimeException;

/**
 * Тестовый издатель: вместо брокера копит опубликованное и умеет «падать» для проверки ретраев.
 */
class FakeEventPublisher implements EventPublisher
{
    /** @var list<array{routingKey: string, payload: array<string, mixed>, messageId: string}> */
    public array $published = [];

    public bool $shouldFail = false;

    public function publish(string $routingKey, array $payload, string $messageId): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('broker unavailable');
        }

        $this->published[] = compact('routingKey', 'payload', 'messageId');
    }
}
