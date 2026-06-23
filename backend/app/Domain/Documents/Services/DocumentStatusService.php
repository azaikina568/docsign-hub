<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentStatusHistory;
use App\Models\User;

class DocumentStatusService
{
    /**
     * Меняет статус документа по разрешённому переходу и фиксирует его в истории.
     * Вызывать внутри транзакции вместе с остальными изменениями.
     */
    public function transition(Document $document, DocumentStatus $to, ?User $actor = null, ?string $reason = null): void
    {
        $from = $document->status;

        if (! $from->canTransitionTo($to)) {
            throw new DocumentStateException("Cannot transition document from {$from->value} to {$to->value}.");
        }

        $document->status = $to;

        if ($to === DocumentStatus::Signed) {
            $document->completed_at = now();
        }

        $document->save();

        DocumentStatusHistory::create([
            'document_id' => $document->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by_user_id' => $actor?->id,
        ]);
    }
}
