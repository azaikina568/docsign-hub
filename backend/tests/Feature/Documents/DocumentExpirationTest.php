<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_command_marks_overdue_documents_with_history(): void
    {
        $owner = User::factory()->create();

        $overduePending = Document::factory()->status(DocumentStatus::Pending)
            ->create(['owner_id' => $owner->id, 'expires_at' => now()->subDay()]);
        $overduePartial = Document::factory()->status(DocumentStatus::PartiallySigned)
            ->create(['owner_id' => $owner->id, 'expires_at' => now()->subMinute()]);

        $this->artisan('documents:expire')->assertSuccessful();

        foreach ([$overduePending, $overduePartial] as $document) {
            $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'expired']);
            $this->assertDatabaseHas('document_status_history', [
                'document_id' => $document->id,
                'to_status' => 'expired',
                'changed_by_user_id' => null,
            ]);
        }
    }

    public function test_document_sent_without_deadline_expires_after_default_ttl(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $document = Document::factory()->create(['owner_id' => $user->id, 'expires_at' => null]);
        DocumentParty::factory()->create(['document_id' => $document->id]);
        Sanctum::actingAs($user, ['access-api']);

        $this->postJson("/api/v1/documents/{$document->ulid}/send")->assertOk();

        $ttl = (int) config('docsign.signing_token_ttl_days');
        $this->travelTo(now()->addDays($ttl + 1));

        $this->artisan('documents:expire')->assertSuccessful();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'expired']);

        $this->travelBack();
    }

    public function test_expire_command_ignores_future_and_final_documents(): void
    {
        $owner = User::factory()->create();

        $future = Document::factory()->status(DocumentStatus::Pending)
            ->create(['owner_id' => $owner->id, 'expires_at' => now()->addDay()]);
        $noExpiry = Document::factory()->status(DocumentStatus::Pending)
            ->create(['owner_id' => $owner->id, 'expires_at' => null]);
        $signed = Document::factory()->status(DocumentStatus::Signed)
            ->create(['owner_id' => $owner->id, 'expires_at' => now()->subDay()]);
        $draft = Document::factory()
            ->create(['owner_id' => $owner->id, 'expires_at' => now()->subDay()]);

        $this->artisan('documents:expire')->assertSuccessful();

        $this->assertDatabaseHas('documents', ['id' => $future->id, 'status' => 'pending']);
        $this->assertDatabaseHas('documents', ['id' => $noExpiry->id, 'status' => 'pending']);
        $this->assertDatabaseHas('documents', ['id' => $signed->id, 'status' => 'signed']);
        $this->assertDatabaseHas('documents', ['id' => $draft->id, 'status' => 'draft']);
    }
}
