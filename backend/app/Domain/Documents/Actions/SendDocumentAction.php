<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
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
     * Переводит документ из draft в pending и выдаёт signing-токены участникам.
     * Возвращает plain-токены (party_id => token) — единственный раз, для demo-уведомления.
     *
     * @return array<int, string>
     */
    public function execute(Document $document, User $actor): array
    {
        if (! $document->status->isDraft()) {
            throw new DocumentStateException('Only draft documents can be sent.');
        }

        $document->loadMissing('parties');

        if ($document->parties->isEmpty()) {
            throw new DocumentStateException('Add at least one party before sending the document.');
        }

        return DB::transaction(function () use ($document, $actor) {
            $plainTokens = [];

            foreach ($document->parties as $party) {
                $plain = Str::random(40);

                $party->signatureToken()->create([
                    'token_hash' => hash('sha256', $plain),
                    'expires_at' => $document->expires_at,
                ]);

                $plainTokens[$party->id] = $plain;
            }

            $this->statusService->transition($document, DocumentStatus::Pending, $actor, 'Document sent for signing.');

            return $plainTokens;
        });
    }
}
