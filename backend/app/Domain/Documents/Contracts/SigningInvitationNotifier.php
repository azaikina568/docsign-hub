<?php

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Data\SigningInvitation;
use App\Domain\Documents\Models\Document;

/**
 * Доставка приглашения на подписание. Доменный слой не знает, как именно
 * (письмо, очередь, внешний сервis) — реализация живёт в Infrastructure.
 */
interface SigningInvitationNotifier
{
    public function notify(Document $document, SigningInvitation $invitation): void;
}
