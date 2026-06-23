<?php

namespace App\Domain\Documents\Listeners;

use App\Domain\Documents\Contracts\SigningInvitationNotifier;
use App\Domain\Documents\Events\DocumentSent;

class SendSigningInvitations
{
    public function __construct(private readonly SigningInvitationNotifier $notifier) {}

    public function handle(DocumentSent $event): void
    {
        foreach ($event->invitations as $invitation) {
            $this->notifier->notify($event->document, $invitation);
        }
    }
}
