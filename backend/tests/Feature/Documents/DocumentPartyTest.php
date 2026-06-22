<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentPartyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_party_to_draft(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->id}/parties", [
            'name' => 'Alice Carter',
            'email' => 'alice@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('document_parties', [
            'document_id' => $document->id,
            'email' => 'alice@example.com',
        ]);
    }

    public function test_party_cannot_be_added_after_send(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->id}/parties", [
            'name' => 'Late Party',
            'email' => 'late@example.com',
        ])->assertStatus(409);
    }

    public function test_owner_can_remove_party_from_draft(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);
        $party = DocumentParty::factory()->create(['document_id' => $document->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/documents/{$document->id}/parties/{$party->id}")->assertOk();
        $this->assertDatabaseMissing('document_parties', ['id' => $party->id]);
    }

    public function test_party_must_belong_to_document_in_url(): void
    {
        $user = User::factory()->create();
        $documentA = Document::factory()->create(['owner_id' => $user->id]);
        $documentB = Document::factory()->create(['owner_id' => $user->id]);
        $party = DocumentParty::factory()->create(['document_id' => $documentB->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/documents/{$documentA->id}/parties/{$party->id}")->assertNotFound();
    }

    public function test_cannot_add_party_to_foreign_document(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/documents/{$document->id}/parties", [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
        ])->assertForbidden();
    }
}
