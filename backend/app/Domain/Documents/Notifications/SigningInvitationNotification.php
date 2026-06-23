<?php

namespace App\Domain\Documents\Notifications;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SigningInvitationNotification extends Notification
{
    public function __construct(
        private readonly Document $document,
        private readonly DocumentParty $party,
        private readonly string $signingUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Please sign: {$this->document->title}")
            ->greeting("Hello {$this->party->name},")
            ->line("You were invited to sign the document \"{$this->document->title}\".")
            ->action('Review and sign', $this->signingUrl)
            ->line('The link is personal — please do not share it.');
    }
}
