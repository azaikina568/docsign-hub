<?php

namespace Tests\Feature\Messaging;

use App\Domain\Documents\Actions\AddDocumentPartyAction;
use App\Domain\Documents\Actions\CancelDocumentAction;
use App\Domain\Documents\Actions\CreateDocumentAction;
use App\Domain\Documents\Actions\ExpireDocumentsAction;
use App\Domain\Documents\Actions\SendDocumentAction;
use App\Domain\Documents\Actions\SignDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Messaging\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Draft с N подписантами, отправленный на подписание. Возвращает [документ, участники].
     *
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

        return [$document, $document->parties()->orderBy('signing_order')->get()->all()];
    }

    private function sign(DocumentParty $party): void
    {
        app(SignDocumentAction::class)->execute($party, null, '127.0.0.1', 'phpunit', $party->signatureToken);
    }

    public function test_creating_document_records_outbox_event(): void
    {
        $owner = User::factory()->create();
        $document = app(CreateDocumentAction::class)->execute($owner, ['title' => 'Consulting']);

        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'document.created',
            'routing_key' => 'document.created.v1',
            'aggregate_type' => 'document',
            'aggregate_id' => $document->ulid,
        ]);
    }

    public function test_outbox_envelope_has_stable_shape(): void
    {
        $owner = User::factory()->create();
        $document = app(CreateDocumentAction::class)->execute($owner, ['title' => 'Consulting']);

        $payload = OutboxMessage::where('event_type', 'document.created')->firstOrFail()->payload;

        $this->assertSame('document.created', $payload['event_type']);
        $this->assertSame('document.created.v1', $payload['routing_key']);
        $this->assertSame($document->ulid, $payload['aggregate_id']);
        $this->assertSame($owner->id, $payload['actor_id']);
        $this->assertArrayHasKey('event_id', $payload);
        $this->assertArrayHasKey('occurred_at', $payload);
        $this->assertSame(['title' => 'Consulting', 'status' => 'draft'], $payload['data']);
    }

    public function test_sending_records_event_without_leaking_tokens(): void
    {
        [$document] = $this->sentDocument(User::factory()->create());

        $sent = OutboxMessage::where('event_type', 'document.sent')
            ->where('aggregate_id', $document->ulid)
            ->firstOrFail();

        $this->assertSame(1, $sent->payload['data']['signers']);
        // Самое важное: ни plain-токенов, ни email подписантов в событии быть не должно.
        $raw = json_encode($sent->payload);
        $this->assertStringNotContainsString('token', $raw);
        $this->assertStringNotContainsString('signer1@example.com', $raw);
    }

    public function test_single_signer_records_signed_and_completed(): void
    {
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 1);

        $this->sign($parties[0]);

        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'document.signed', 'aggregate_id' => $document->ulid]);
        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'document.completed', 'aggregate_id' => $document->ulid]);
    }

    public function test_partial_signing_records_signed_without_completed(): void
    {
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 2);

        $this->sign($parties[0]);

        $this->assertSame(1, OutboxMessage::where('event_type', 'document.signed')->where('aggregate_id', $document->ulid)->count());
        $this->assertSame(0, OutboxMessage::where('event_type', 'document.completed')->where('aggregate_id', $document->ulid)->count());
    }

    public function test_repeat_sign_does_not_duplicate_event(): void
    {
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 1);

        $this->sign($parties[0]);
        $this->sign($parties[0]); // идемпотентный повтор

        $this->assertSame(1, OutboxMessage::where('event_type', 'document.signed')->where('aggregate_id', $document->ulid)->count());
    }

    public function test_failed_sign_writes_no_outbox_event(): void
    {
        [$document, $parties] = $this->sentDocument(User::factory()->create(), 2);

        // Второй по очереди не может подписать раньше первого — действие откатывается целиком.
        try {
            $this->sign($parties[1]);
        } catch (\Throwable) {
            // ожидаемо
        }

        $this->assertSame(0, OutboxMessage::where('event_type', 'document.signed')->where('aggregate_id', $document->ulid)->count());
    }

    public function test_cancel_records_event(): void
    {
        [$document] = $this->sentDocument(User::factory()->create());
        $owner = $document->owner()->firstOrFail();

        app(CancelDocumentAction::class)->execute($document, $owner, 'Client postponed');

        $cancelled = OutboxMessage::where('event_type', 'document.cancelled')->where('aggregate_id', $document->ulid)->firstOrFail();
        $this->assertSame('Client postponed', $cancelled->payload['data']['reason']);
    }

    public function test_expire_records_event(): void
    {
        [$document] = $this->sentDocument(User::factory()->create());
        $document->update(['expires_at' => now()->subDay()]);

        app(ExpireDocumentsAction::class)->execute();

        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'document.expired', 'aggregate_id' => $document->ulid]);
    }
}
