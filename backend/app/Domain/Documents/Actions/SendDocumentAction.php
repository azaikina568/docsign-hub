<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Data\SigningInvitation;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Events\DocumentSent;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\SignatureToken;
use App\Domain\Documents\Services\DocumentStatusService;
use App\Domain\Messaging\Data\OutboxEvent;
use App\Domain\Messaging\Services\OutboxWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendDocumentAction
{
    public function __construct(
        private readonly DocumentStatusService $statusService,
        private readonly OutboxWriter $outbox,
    ) {}

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

        // Дедлайн один на документ и его токены. Если владелец не задал expires_at —
        // берём дефолтный TTL и фиксируем его на документе, иначе documents:expire
        // никогда не закроет заброшенный документ (фильтр по expires_at).
        $deadline = $document->expires_at ?? now()->addDays((int) config('docsign.signing_token_ttl_days'));

        $invitations = DB::transaction(function () use ($document, $actor, $signers, $deadline) {
            $document->expires_at = $deadline;
            // Снимок подписываемого: подписи привязываются к нему через content_hash,
            // поэтому позднейшее изменение содержимого детектируется (см. SIGNING_SECURITY.md).
            $document->content_hash = $this->contentHash($document);

            $invitations = [];

            foreach ($signers as $party) {
                $plain = Str::random(40);

                SignatureToken::create([
                    'document_party_id' => $party->id,
                    'token_hash' => hash('sha256', $plain),
                    'expires_at' => $deadline,
                ]);

                $invitations[] = new SigningInvitation($party, $plain);
            }

            // transition() сохраняет документ целиком — вместе с проставленным expires_at.
            $this->statusService->transition($document, DocumentStatus::Pending, $actor, 'Document sent for signing.');

            // В payload только метаданные — без plain-токенов и email подписантов (их детали добирает consumer).
            $this->outbox->record(OutboxEvent::make('document.sent', $document->ulid, $actor->id, [
                'title' => $document->title,
                'signers' => $signers->count(),
                'expires_at' => $document->expires_at?->toISOString(),
            ]));

            return $invitations;
        });

        // Приглашения рассылаем после коммита: сбой доставки не должен откатывать отправку.
        DocumentSent::dispatch($document, $invitations);

        return $document;
    }

    /**
     * Канонический снимок документа: title + участники в стабильном порядке.
     * В будущем сюда добавится хеш реального файла документа.
     */
    private function contentHash(Document $document): string
    {
        $snapshot = [
            'title' => $document->title,
            'parties' => $document->parties
                ->sortBy([['signing_order', 'asc'], ['email', 'asc']])
                ->map(fn ($party) => [
                    'email' => $party->email,
                    'role' => $party->role->value,
                    'signing_order' => $party->signing_order,
                ])
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
