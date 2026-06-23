<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExtendDocumentDeadlineAction
{
    /**
     * Двигает дедлайн активного документа вперёд и синхронно продлевает срок ещё не
     * использованных токенов (инвариант «срок документа = срок токенов»). Терминальный
     * документ продлевать нельзя; назад дедлайн не сдвигаем.
     */
    public function execute(Document $document, User $actor, Carbon $newDeadline): Document
    {
        if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::PartiallySigned], true)) {
            throw new DocumentStateException('Only documents awaiting signatures can have their deadline extended.');
        }

        if ($document->expires_at !== null && $newDeadline->lessThanOrEqualTo($document->expires_at)) {
            throw new DocumentStateException('The new deadline must be later than the current one.');
        }

        return DB::transaction(function () use ($document, $actor, $newDeadline): Document {
            $previous = $document->expires_at;

            $document->expires_at = $newDeadline;
            $document->save();

            // Продлеваем только живые токены: использованные/чужих документов не трогаем.
            $document->parties()
                ->whereHas('signatureToken', fn ($query) => $query->whereNull('used_at'))
                ->with('signatureToken')
                ->get()
                ->each(fn ($party) => $party->signatureToken?->update(['expires_at' => $newDeadline]));

            // Продление — событие аудита без смены статуса, поэтому пишем history напрямую
            // (карта переходов self-loop не допускает).
            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => $document->status,
                'to_status' => $document->status,
                'reason' => sprintf(
                    'Deadline extended%s to %s.',
                    $previous !== null ? ' from '.$previous->toDateString() : '',
                    $newDeadline->toDateString(),
                ),
                'changed_by_user_id' => $actor->id,
            ]);

            return $document;
        });
    }
}
