<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_moves_to_pending_and_records_event_without_leaking_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);
        $party = DocumentParty::factory()->create(['document_id' => $document->id, 'email' => 'signer@example.com']);

        Sanctum::actingAs($user, ['access-api']);

        $response = $this->postJson("/api/v1/documents/{$document->ulid}/send");

        $response->assertOk()->assertJsonPath('data.status', 'pending');
        // Токены не возвращаются отправителю.
        $response->assertJsonMissingPath('signing_tokens');

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'pending']);
        $this->assertDatabaseHas('document_status_history', [
            'document_id' => $document->id,
            'from_status' => 'draft',
            'to_status' => 'pending',
        ]);

        // Токен хранится только хешем и со сроком жизни.
        $token = $party->signatureToken()->firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertSame(64, strlen($token->token_hash));

        // Отправка фиксирует событие document.sent (на нём consumer строит рассылку приглашений),
        // но синхронно письмо не уходит — доставка вынесена за очередь (Этап 5c).
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'document.sent',
            'aggregate_id' => $document->ulid,
        ]);
        Notification::assertNothingSent();
    }

    public function test_send_sets_document_deadline_matching_token_expiry(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access-api']);

        // Без явного expires_at — проставляется дефолтный TTL, и документ становится способным истечь.
        $auto = Document::factory()->create(['owner_id' => $user->id, 'expires_at' => null]);
        DocumentParty::factory()->create(['document_id' => $auto->id]);
        $this->postJson("/api/v1/documents/{$auto->ulid}/send")->assertOk();
        $this->assertNotNull($auto->fresh()->expires_at);

        // Явный дедлайн владельца сохраняется и совпадает со сроком токена.
        $explicit = Document::factory()->create(['owner_id' => $user->id, 'expires_at' => now()->addDays(3)]);
        $party = DocumentParty::factory()->create(['document_id' => $explicit->id]);
        $this->postJson("/api/v1/documents/{$explicit->ulid}/send")->assertOk();

        $token = $party->signatureToken()->firstOrFail();
        $this->assertSame(
            $explicit->fresh()->expires_at?->toDateTimeString(),
            $token->expires_at?->toDateTimeString(),
        );
    }

    public function test_document_without_signers_cannot_be_sent(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);
        DocumentParty::factory()->create([
            'document_id' => $document->id,
            'role' => PartyRole::Viewer,
        ]);

        Sanctum::actingAs($user, ['access-api']);

        $this->postJson("/api/v1/documents/{$document->ulid}/send")->assertStatus(409);
    }

    public function test_owner_can_cancel_non_final_document(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user, ['access-api']);

        $this->postJson("/api/v1/documents/{$document->ulid}/cancel", ['reason' => 'No longer needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('document_status_history', [
            'document_id' => $document->id,
            'to_status' => 'cancelled',
            'reason' => 'No longer needed',
        ]);
    }

    public function test_final_document_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Signed)->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user, ['access-api']);

        $this->postJson("/api/v1/documents/{$document->ulid}/cancel")->assertStatus(409);
    }

    public function test_events_endpoint_returns_status_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access-api']);

        $ulid = $this->postJson('/api/v1/documents', ['title' => 'Tracked'])->json('data.id');

        $this->getJson("/api/v1/documents/{$ulid}/events")
            ->assertOk()
            ->assertJsonPath('data.0.to_status', 'draft');
    }

    public function test_events_are_paginated_and_sortable(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access-api']);

        // Создаём через API, чтобы в истории появилась запись draft, затем send добавляет pending.
        $ulid = $this->postJson('/api/v1/documents', ['title' => 'Tracked'])->json('data.id');
        $document = Document::query()->where('ulid', $ulid)->firstOrFail();
        DocumentParty::factory()->create(['document_id' => $document->id]);

        $this->postJson("/api/v1/documents/{$ulid}/send")->assertOk();

        // По умолчанию — свежие события первыми (draft -> pending).
        $this->getJson("/api/v1/documents/{$ulid}/events")
            ->assertOk()
            ->assertJsonPath('data.0.to_status', 'pending')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson("/api/v1/documents/{$ulid}/events?sort=asc")
            ->assertOk()
            ->assertJsonPath('data.0.to_status', 'draft');
    }
}
