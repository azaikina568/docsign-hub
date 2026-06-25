<?php

namespace App\Domain\Documents\Notifications;

use App\Domain\Documents\Models\Document;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Уведомление владельцу: документ истёк, не собрав все подписи (до 5c экспирация была «молчаливой»).
 */
class DocumentExpiredNotification extends Notification
{
    public function __construct(private readonly Document $document) {}

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
            ->subject("Signing deadline passed: {$this->document->title}")
            ->line("The document \"{$this->document->title}\" expired before all signers completed it.")
            ->line('You can duplicate it to start a fresh signing round.');
    }
}
