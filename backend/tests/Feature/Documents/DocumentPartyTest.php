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

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
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

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
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

        $this->deleteJson("/api/v1/documents/{$document->ulid}/parties/{$party->id}")->assertOk();
        $this->assertDatabaseMissing('document_parties', ['id' => $party->id]);
    }

    public function test_party_must_belong_to_document_in_url(): void
    {
        $user = User::factory()->create();
        $documentA = Document::factory()->create(['owner_id' => $user->id]);
        $documentB = Document::factory()->create(['owner_id' => $user->id]);
        $party = DocumentParty::factory()->create(['document_id' => $documentB->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/documents/{$documentA->ulid}/parties/{$party->id}")->assertNotFound();
    }

    public function test_signing_order_applies_to_signers_not_viewers(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
            'name' => 'Signer', 'email' => 'signer@example.com', 'signing_order' => 2,
        ])->assertCreated()->assertJsonPath('data.signing_order', 2);

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
            'name' => 'Viewer', 'email' => 'viewer@example.com', 'role' => 'viewer', 'signing_order' => 3,
        ])->assertCreated()->assertJsonPath('data.signing_order', null);

        $this->assertDatabaseHas('document_parties', ['email' => 'viewer@example.com', 'signing_order' => null]);
    }

    public function test_duplicate_party_email_is_rejected(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);
        DocumentParty::factory()->create(['document_id' => $document->id, 'email' => 'dup@example.com']);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
            'name' => 'Duplicate',
            'email' => 'dup@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_party_is_linked_to_registered_user_by_email(): void
    {
        $owner = User::factory()->create();
        $signerAccount = User::factory()->create(['email' => 'member@example.com']);
        $document = Document::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
            'name' => 'Member',
            'email' => 'member@example.com',
        ])->assertCreated();
        $this->assertDatabaseHas('document_parties', [
            'email' => 'member@example.com',
            'user_id' => $signerAccount->id,
        ]);

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
        ])->assertCreated();
        $this->assertDatabaseHas('document_parties', [
            'email' => 'outsider@example.com',
            'user_id' => null,
        ]);
    }

    public function test_cannot_add_party_to_foreign_document(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/documents/{$document->ulid}/parties", [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
        ])->assertForbidden();
    }
}
