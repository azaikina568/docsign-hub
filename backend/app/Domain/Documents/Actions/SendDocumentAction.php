<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Data\SigningInvitation;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Events\DocumentSent;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentStatusService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendDocumentAction
{
    public function __construct(private readonly DocumentStatusService $statusService) {}

    /**
     * Переводит документ из draft в pending, выдаёт signing-токены подписантам
     * и публикует событие DocumentSent (приглашения уходят участникам, не отправителю).
     */
    public function execute(Document $document, User $actor): Document
    {
        if (! $document->status->isDraft()) {
            throw new DocumentStateException('Only draft documents can be sent.');
        }

        $document->loadMissing('parties');

        $signers = $document->parties->where('role', PartyRole::Signer);

        if ($signers->isEmpty()) {
            throw new DocumentStateException('Add at least one signer before sending the document.');
        }

        $ttl = now()->addDays((int) config('docsign.signing_token_ttl_days'));
        $expiresAt = $document->expires_at ?? $ttl;

        $invitations = DB::transaction(function () use ($document, $actor, $signers, $expiresAt) {
            $invitations = [];

            foreach ($signers as $party) {
                $plain = Str::random(40);

                $party->signatureToken()->create([
                    'token_hash' => hash('sha256', $plain),
                    'expires_at' => $expiresAt,
                ]);

                $invitations[] = new SigningInvitation($party, $plain);
            }

            $this->statusService->transition($document, DocumentStatus::Pending, $actor, 'Document sent for signing.');

            return $invitations;
        });

        // Приглашения рассылаем после коммита: сбой доставки не должен откатывать отправку.
        DocumentSent::dispatch($document, $invitations);

        return $document;
    }
}
