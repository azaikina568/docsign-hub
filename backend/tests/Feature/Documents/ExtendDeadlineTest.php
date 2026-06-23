<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Models\SignatureToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExtendDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_extends_deadline_and_unused_token_expiry(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create([
            'owner_id' => $owner->id,
            'expires_at' => now()->addDays(3),
        ]);
        $party = DocumentParty::factory()->create(['document_id' => $document->id]);
        $token = SignatureToken::create([
            'document_party_id' => $party->id,
            'token_hash' => hash('sha256', 'plain'),
            'expires_at' => $document->expires_at,
        ]);

        Sanctum::actingAs($owner, ['access-api']);

        $newDeadline = now()->addDays(10)->startOfSecond();

        $this->patchJson("/api/v1/documents/{$document->ulid}/deadline", [
            'expires_at' => $newDeadline->toISOString(),
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertSame(
            $newDeadline->toDateTimeString(),
            $document->fresh()->expires_at?->toDateTimeString(),
        );
        // Срок документа = срок неиспользованных токенов.
        $this->assertSame(
            $newDeadline->toDateTimeString(),
            $token->fresh()->expires_at?->toDateTimeString(),
        );
        $this->assertDatabaseHas('document_status_history', [
            'document_id' => $document->id,
            'from_status' => 'pending',
            'to_status' => 'pending',
        ]);
    }

    public function test_deadline_cannot_move_backwards(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create([
            'owner_id' => $owner->id,
            'expires_at' => now()->addDays(10),
        ]);

        Sanctum::actingAs($owner, ['access-api']);

        $this->patchJson("/api/v1/documents/{$document->ulid}/deadline", [
            'expires_at' => now()->addDays(5)->toISOString(),
        ])->assertStatus(409);
    }

    public function test_terminal_document_deadline_cannot_be_extended(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Signed)->create([
            'owner_id' => $owner->id,
            'expires_at' => now()->addDays(3),
        ]);

        Sanctum::actingAs($owner, ['access-api']);

        $this->patchJson("/api/v1/documents/{$document->ulid}/deadline", [
            'expires_at' => now()->addDays(20)->toISOString(),
        ])->assertStatus(409);
    }

    public function test_only_owner_can_extend_deadline(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create([
            'owner_id' => $owner->id,
            'expires_at' => now()->addDays(3),
        ]);

        Sanctum::actingAs(User::factory()->create(), ['access-api']);

        $this->patchJson("/api/v1/documents/{$document->ulid}/deadline", [
            'expires_at' => now()->addDays(20)->toISOString(),
        ])->assertForbidden();
    }

    public function test_past_deadline_is_rejected_by_validation(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->status(DocumentStatus::Pending)->create([
            'owner_id' => $owner->id,
            'expires_at' => now()->addDays(3),
        ]);

        Sanctum::actingAs($owner, ['access-api']);

        $this->patchJson("/api/v1/documents/{$document->ulid}/deadline", [
            'expires_at' => now()->subDay()->toISOString(),
        ])->assertStatus(422)->assertJsonValidationErrors('expires_at');
    }
}
