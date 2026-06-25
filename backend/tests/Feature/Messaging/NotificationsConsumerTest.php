<?php

namespace Tests\Feature\Messaging;

use App\Domain\Documents\Actions\AddDocumentPartyAction;
use App\Domain\Documents\Actions\CreateDocumentAction;
use App\Domain\Documents\Actions\SendDocumentAction;
use App\Domain\Documents\Actions\SignDocumentAction;
use App\Domain\Documents\Consumers\NotificationsConsumer;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Notifications\DocumentExpiredNotification;
use App\Domain\Documents\Notifications\SigningInvitationNotification;
use App\Domain\Messaging\Data\InboundEvent;
use App\Domain\Messaging\Enums\DomainEventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsConsumerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Document, 1: list<DocumentParty>}
     */
    private function sentDocument(User $owner, int $signers = 1): array
    {
        $document = app(CreateDocumentAction::class)->execute($owner, ['title' => 'Agreement']);

        for ($order = 1; $order <= $signers; $order++) {
            app(AddDocumentPartyAction::class)->execute($document, [
                'name' => "Signer {$order}",
                'email' => "signer{$order}@example.com",
                'signing_order' => $order,
            ]);
        }

        app(SendDocumentAction::class)->execute($document->load('parties'), $owner);

        return [$document->refresh(), $document->parties()->orderBy('signing_order')->get()->all()];
    }

    private function event(DomainEventType $type, Document $document): InboundEvent
    {
        return InboundEvent::fromEnvelope([
            'event_id' => (string) Str::orderedUuid(),
            'event_type' => $type->value,
            'routing_key' => $type->routingKey(),
            'version' => 1,
            'occurred_at' => now()->toISOString(),
            'aggregate_type' => 'document',
            'aggregate_id' => $document->ulid,
            'actor_id' => null,
            'data' => [],
        ]);
    }

    private function consume(InboundEvent $event): void
    {
        app(NotificationsConsumer::class)->handle($event);
    }

    public function test_sent_event_invites_only_first_signer(): void
    {
        Notification::fake();
        [$document] = $this->sentDocument(User::factory()->create(), 3);

        $this->consume($this->event(DomainEventType::DocumentSent, $document));

        // Приглашение получает только первый по очереди, остальные ждут (ровно одно письмо).
        $this->assertInvited('signer1@example.com');
        Notification::assertCount(1);
    }

    public function test_signed_event_invites_next_signer_only(): void
    {
        Notification::fake();
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 3);

        app(SignDocumentAction::class)->execute($parties[0], null, '127.0.0.1', 'phpunit', $parties[0]->signatureToken);

        $this->consume($this->event(DomainEventType::DocumentSigned, $document->refresh()));

        // После подписи первого приглашение уходит второму — и только ему (одно письмо).
        $this->assertInvited('signer2@example.com');
        Notification::assertCount(1);
    }

    public function test_invitation_is_idempotent_per_signer(): void
    {
        Notification::fake();
        [$document] = $this->sentDocument(User::factory()->create(), 2);

        // Несколько триггеров на одного и того же текущего подписанта (повтор sent, гонка sent+signed)
        // не должны слать второе письмо и перевыпускать токен — иначе первая ссылка стала бы битой.
        $this->consume($this->event(DomainEventType::DocumentSent, $document));
        $this->consume($this->event(DomainEventType::DocumentSigned, $document->refresh()));
        $this->consume($this->event(DomainEventType::DocumentSent, $document->refresh()));

        $this->assertInvited('signer1@example.com');
        Notification::assertCount(1);
    }

    public function test_signed_event_on_completed_document_invites_nobody(): void
    {
        Notification::fake();
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 1);

        app(SignDocumentAction::class)->execute($parties[0], null, '127.0.0.1', 'phpunit', $parties[0]->signatureToken);

        $this->consume($this->event(DomainEventType::DocumentSigned, $document->refresh()));

        Notification::assertNothingSent();
    }

    public function test_delivery_remints_token_so_emailed_link_is_fresh(): void
    {
        Notification::fake();
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 1);

        $before = $parties[0]->signatureToken()->firstOrFail()->token_hash;

        $this->consume($this->event(DomainEventType::DocumentSent, $document));

        // Доставка перевыпускает токен: в БД лежит новый хеш, plain ушёл только письмом.
        $after = $parties[0]->signatureToken()->firstOrFail()->token_hash;
        $this->assertNotSame($before, $after);
    }

    public function test_expired_event_notifies_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        [$document] = $this->sentDocument($owner, 1);

        $this->consume($this->event(DomainEventType::DocumentExpired, $document));

        Notification::assertSentTo($owner, DocumentExpiredNotification::class);
    }

    private function assertInvited(string $email): void
    {
        Notification::assertSentOnDemand(
            SigningInvitationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $email,
        );
    }
}
