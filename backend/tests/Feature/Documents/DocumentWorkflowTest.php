<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_moves_document_to_pending_and_issues_hashed_tokens(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);
        DocumentParty::factory()->create(['document_id' => $document->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/documents/{$document->id}/send");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data', 'signing_tokens']);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'pending']);
        $this->assertDatabaseHas('document_status_history', [
            'document_id' => $document->id,
            'from_status' => 'draft',
            'to_status' => 'pending',
        ]);

        $plain = array_values($response->json('signing_tokens'))[0];
        $this->assertDatabaseMissing('signature_tokens', ['token_hash' => $plain]);
        $this->assertDatabaseHas('signature_tokens', ['token_hash' => hash('sha256', $plain)]);
    }

    public function test_document_without_parties_cannot_be_sent(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);

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
