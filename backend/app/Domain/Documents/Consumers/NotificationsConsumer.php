<?php

namespace App\Domain\Documents\Consumers;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Notifications\DocumentExpiredNotification;
use App\Domain\Documents\Services\SigningInvitationCoordinator;
use App\Domain\Messaging\Contracts\EventConsumer;
use App\Domain\Messaging\Data\InboundEvent;
use App\Domain\Messaging\Enums\DomainEventType;

/**
 * Consumer уведомлений: превращает доменные события в письма.
 * - sent/signed → приглашение текущему по очереди подписанту (поэтапная рассылка, OPEN_QUESTIONS Q3);
 * - expired → уведомление владельцу о просроченном документе.
 * Сама доставка писем уехала сюда из синхронного listener'а — запрос на send больше не блокируется почтой.
 */
class NotificationsConsumer implements EventConsumer
{
    public function __construct(private readonly SigningInvitationCoordinator $coordinator) {}

    public function name(): string
    {
        return 'notifications';
    }

    public function queue(): string
    {
        return 'docsign.notifications';
    }

    public function handle(InboundEvent $event): void
    {
        $document = $this->resolveDocument($event);

        if ($document === null) {
            return;
        }

        match ($event->eventType) {
            // На отправку и на каждую подпись (если документ не завершён) зовём следующего по очереди.
            DomainEventType::DocumentSent->value,
            DomainEventType::DocumentSigned->value => $this->coordinator->inviteCurrentSigner($document),
            DomainEventType::DocumentExpired->value => $document->owner?->notify(new DocumentExpiredNotification($document)),
            // completed/cancelled/created уведомлений пока не порождают.
            default => null,
        };
    }

    private function resolveDocument(InboundEvent $event): ?Document
    {
        if ($event->aggregateId === null) {
            return null;
        }

        return Document::query()->where('ulid', $event->aggregateId)->first();
    }
}
