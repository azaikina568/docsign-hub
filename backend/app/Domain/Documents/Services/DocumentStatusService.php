<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Models\User;

class DocumentStatusService
{
    /**
     * Меняет статус документа и фиксирует переход в истории.
     * Вызывать внутри транзакции вместе с остальными изменениями.
     */
    public function transition(Document $document, DocumentStatus $to, ?User $actor = null, ?string $reason = null): void
    {
        $from = $document->status;

        $document->status = $to;

        if ($to === DocumentStatus::Signed) {
            $document->completed_at = now();
        }

        $document->save();

        $document->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by_user_id' => $actor?->id,
        ]);
    }
}
