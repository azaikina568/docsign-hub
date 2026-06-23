<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/documents')->assertUnauthorized();
    }

    public function test_owner_can_create_document_as_draft_with_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/documents', ['title' => 'Service contract']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Service contract')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('documents', [
            'title' => 'Service contract',
            'owner_id' => $user->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('document_status_history', [
            'to_status' => 'draft',
            'changed_by_user_id' => $user->id,
        ]);
    }

    public function test_create_document_requires_title(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/documents', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_index_lists_only_owned_documents(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Document::factory()->create(['owner_id' => $user->id]);
        Document::factory()->create(['owner_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_document_is_addressed_by_ulid_not_int_id(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/documents/{$document->ulid}")
            ->assertOk()
            ->assertJsonPath('data.id', $document->ulid);

        // Внутренний int PK наружу не выставляется и не резолвится в URL.
        $this->getJson("/api/v1/documents/{$document->id}")->assertNotFound();
    }

    public function test_unknown_ulid_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/documents/'.Str::ulid())->assertNotFound();
    }

    public function test_owner_cannot_view_foreign_document(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/documents/{$document->ulid}")->assertForbidden();
    }

    public function test_owner_can_update_draft_document(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/documents/{$document->ulid}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_non_draft_document_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create(['owner_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/documents/{$document->ulid}", ['title' => 'Nope'])
            ->assertStatus(409);
    }

    public function test_draft_can_be_deleted_but_sent_cannot(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draft = Document::factory()->create(['owner_id' => $user->id]);
        $this->deleteJson("/api/v1/documents/{$draft->ulid}")->assertOk();
        $this->assertDatabaseMissing('documents', ['id' => $draft->id]);

        $pending = Document::factory()->status(DocumentStatus::Pending)->create(['owner_id' => $user->id]);
        $this->deleteJson("/api/v1/documents/{$pending->ulid}")->assertStatus(409);
        $this->assertDatabaseHas('documents', ['id' => $pending->id]);
    }
}
