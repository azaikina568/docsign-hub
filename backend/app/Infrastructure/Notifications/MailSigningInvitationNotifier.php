<?php

namespace App\Infrastructure\Notifications;

use App\Domain\Documents\Contracts\SigningInvitationNotifier;
use App\Domain\Documents\Data\SigningInvitation;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Notifications\SigningInvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Доставка приглашения письмом (локально — Mailpit). Plain-токен уходит только
 * подписанту на его email и нигде не логируется.
 */
class MailSigningInvitationNotifier implements SigningInvitationNotifier
{
    public function notify(Document $document, SigningInvitation $invitation): void
    {
        $url = rtrim((string) config('docsign.signing_url_base'), '/').'/'.$invitation->plainToken;

        Notification::route('mail', $invitation->party->email)
            ->notify(new SigningInvitationNotification($document, $invitation->party, $url));
    }
}
