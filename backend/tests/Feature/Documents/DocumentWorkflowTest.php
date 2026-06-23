<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Notifications\SigningInvitationNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_moves_to_pending_and_invites_signers_without_leaking_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);
        $party = DocumentParty::factory()->create(['document_id' => $document->id, 'email' => 'signer@example.com']);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/documents/{$document->id}/send");

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

        // Приглашение уходит подписанту на его email, а не отправителю.
        Notification::assertSentOnDemand(
            SigningInvitationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'signer@example.com',
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

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->id}/send")->assertStatus(409);
    }

    public function test_owner_can_cancel_non_final_document(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->id}/cancel", ['reason' => 'No longer needed'])
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

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->id}/cancel")->assertStatus(409);
    }

    public function test_events_endpoint_returns_status_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = Document::query()->find(
            $this->postJson('/api/v1/documents', ['title' => 'Tracked'])->json('data.id')
        );

        $this->getJson("/api/v1/documents/{$document->id}/events")
            ->assertOk()
            ->assertJsonPath('data.0.to_status', 'draft');
    }
}
