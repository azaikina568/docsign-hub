<?php

namespace Tests\Feature\Documents;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Models\SignatureToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SigningFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Создаёт pending-документ с подписантом и его capability-токеном, возвращает plain-токен.
     *
     * @param  array<string, mixed>  $partyAttrs
     * @return array{0: Document, 1: DocumentParty, 2: string}
     */
    private function pendingDocumentWithSigner(User $owner, array $partyAttrs = [], ?CarbonInterface $expiresAt = null): array
    {
        $document = Document::factory()->status(DocumentStatus::Pending)->create([
            'owner_id' => $owner->id,
            'content_hash' => hash('sha256', 'snapshot'),
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);

        $party = DocumentParty::factory()->create(array_merge([
            'document_id' => $document->id,
            'signing_order' => 1,
            'status' => PartyStatus::Pending,
        ], $partyAttrs));

        $plain = Str::random(40);
        SignatureToken::create([
            'document_party_id' => $party->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $document->expires_at,
        ]);

        return [$document, $party, $plain];
    }

    public function test_external_signer_signs_with_token_and_completes_document(): void
    {
        $owner = User::factory()->create();
        [$document, $party, $token] = $this->pendingDocumentWithSigner($owner);

        $this->postJson("/api/v1/signing/{$token}/sign")
            ->assertCreated()
            ->assertJsonPath('data.party_id', $party->id)
            ->assertJsonStructure(['data' => ['id', 'party_id', 'signature_hash', 'signed_at']]);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'signed']);
        $this->assertDatabaseHas('document_parties', ['id' => $party->id, 'status' => 'signed']);
        $this->assertDatabaseHas('signatures', ['document_party_id' => $party->id]);
        // Токен погашен после использования.
        $this->assertNotNull($party->signatureToken()->firstOrFail()->used_at);
    }

    public function test_signing_context_exposes_own_party_and_progress_only(): void
    {
        $owner = User::factory()->create();
        [$document, $party, $token] = $this->pendingDocumentWithSigner($owner);
        // Второй подписант — его email не должен утечь в контекст первого.
        DocumentParty::factory()->create([
            'document_id' => $document->id,
            'email' => 'secret-other@example.com',
            'signing_order' => 2,
        ]);

        $response = $this->getJson("/api/v1/signing/{$token}")
            ->assertOk()
            ->assertJsonPath('data.party.email', $party->email)
            ->assertJsonPath('data.progress.total', 2)
            ->assertJsonPath('data.progress.signed', 0)
            ->assertJsonPath('data.your_turn', true)
            ->assertJsonPath('data.can_sign', true);

        $this->assertStringNotContainsString('secret-other@example.com', $response->getContent() ?: '');
    }

    public function test_two_signers_progress_partially_then_fully_signed(): void
    {
        $owner = User::factory()->create();
        [$document, $first, $firstToken] = $this->pendingDocumentWithSigner($owner, ['signing_order' => 1]);

        $second = DocumentParty::factory()->create([
            'document_id' => $document->id,
            'signing_order' => 2,
        ]);
        $secondPlain = Str::random(40);
        SignatureToken::create([
            'document_party_id' => $second->id,
            'token_hash' => hash('sha256', $secondPlain),
            'expires_at' => $document->expires_at,
        ]);

        $this->postJson("/api/v1/signing/{$firstToken}/sign")->assertCreated();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'partially_signed']);

        $this->postJson("/api/v1/signing/{$secondPlain}/sign")->assertCreated();
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'signed']);
    }

    public function test_signer_cannot_sign_out_of_order(): void
    {
        $owner = User::factory()->create();
        [$document] = $this->pendingDocumentWithSigner($owner, ['signing_order' => 1]);

        $second = DocumentParty::factory()->create(['document_id' => $document->id, 'signing_order' => 2]);
        $secondPlain = Str::random(40);
        SignatureToken::create([
            'document_party_id' => $second->id,
            'token_hash' => hash('sha256', $secondPlain),
            'expires_at' => $document->expires_at,
        ]);

        // Второй по очереди не может подписать, пока не подписал первый.
        $this->postJson("/api/v1/signing/{$secondPlain}/sign")->assertStatus(409);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'pending']);
    }

    public function test_repeat_sign_is_idempotent(): void
    {
        $owner = User::factory()->create();
        [$document, $party, $token] = $this->pendingDocumentWithSigner($owner);

        $first = $this->postJson("/api/v1/signing/{$token}/sign")->assertCreated();
        // Повторная подпись по той же ссылке не создаёт дубль и не падает.
        $second = $this->postJson("/api/v1/signing/{$token}/sign")->assertOk();

        $this->assertSame($first->json('data.signature_hash'), $second->json('data.signature_hash'));
        $this->assertSame(1, $party->signature()->count());
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->getJson('/api/v1/signing/'.Str::random(40))->assertNotFound();
        $this->postJson('/api/v1/signing/'.Str::random(40).'/sign')->assertNotFound();
    }

    public function test_signing_after_deadline_returns_410(): void
    {
        $owner = User::factory()->create();
        [, , $token] = $this->pendingDocumentWithSigner($owner, [], now()->subDay());

        $this->postJson("/api/v1/signing/{$token}/sign")->assertStatus(410);
    }

    public function test_viewer_token_cannot_sign(): void
    {
        $owner = User::factory()->create();
        [, , $token] = $this->pendingDocumentWithSigner($owner, [
            'role' => PartyRole::Viewer,
            'signing_order' => null,
        ]);

        $this->postJson("/api/v1/signing/{$token}/sign")->assertForbidden();
    }

    public function test_signing_on_cancelled_document_is_conflict(): void
    {
        $owner = User::factory()->create();
        [$document, , $token] = $this->pendingDocumentWithSigner($owner);
        $document->update(['status' => DocumentStatus::Cancelled]);

        $this->postJson("/api/v1/signing/{$token}/sign")->assertStatus(409);
    }

    public function test_account_bound_party_requires_matching_identity(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        [, $party, $token] = $this->pendingDocumentWithSigner($owner, [
            'email' => 'member@example.com',
            'user_id' => $member->id,
        ]);

        // Без логина — capability-токена мало.
        $this->postJson("/api/v1/signing/{$token}/sign")->assertForbidden();

        // Чужой логин — тоже нельзя.
        Sanctum::actingAs(User::factory()->create(), ['access-api']);
        $this->postJson("/api/v1/signing/{$token}/sign")->assertForbidden();

        // Сам участник по ссылке — можно.
        Sanctum::actingAs($member, ['access-api']);
        $this->postJson("/api/v1/signing/{$token}/sign")->assertCreated();
        $this->assertDatabaseHas('document_parties', ['id' => $party->id, 'status' => 'signed']);
    }

    public function test_registered_user_signs_from_dashboard(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        [, $party] = $this->pendingDocumentWithSigner($owner, [
            'email' => 'member@example.com',
            'user_id' => $member->id,
        ]);

        Sanctum::actingAs($member, ['access-api']);

        $this->getJson('/api/v1/signing-requests')
            ->assertOk()
            ->assertJsonPath('data.0.party_id', $party->id);

        $this->postJson("/api/v1/signing-requests/{$party->id}/sign")->assertCreated();
        $this->assertDatabaseHas('document_parties', ['id' => $party->id, 'status' => 'signed']);
        // Висящий emailed-токен тоже погашен.
        $this->assertNotNull($party->signatureToken()->firstOrFail()->used_at);
    }

    public function test_dashboard_sign_rejects_foreign_party(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        [, $party] = $this->pendingDocumentWithSigner($owner, [
            'email' => 'member@example.com',
            'user_id' => $member->id,
        ]);

        // Другой залогиненный пользователь не подписывает за чужое участие.
        Sanctum::actingAs(User::factory()->create(), ['access-api']);
        $this->postJson("/api/v1/signing-requests/{$party->id}/sign")->assertForbidden();
    }
}
