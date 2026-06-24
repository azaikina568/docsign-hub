<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentStatusService;
use App\Domain\Messaging\Data\OutboxEvent;
use App\Domain\Messaging\Services\OutboxWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelDocumentAction
{
    public function __construct(
        private readonly DocumentStatusService $statusService,
        private readonly OutboxWriter $outbox,
    ) {}

    public function execute(Document $document, User $actor, ?string $reason = null): Document
    {
        if (! $document->status->canBeCancelled()) {
            throw new DocumentStateException('Document can no longer be cancelled.');
        }

        $reason ??= 'Document cancelled by owner.';

        DB::transaction(function () use ($document, $actor, $reason) {
            $this->statusService->transition($document, DocumentStatus::Cancelled, $actor, $reason);

            $this->outbox->record(OutboxEvent::make('document.cancelled', $document->ulid, $actor->id, [
                'reason' => $reason,
            ]));
        });

        return $document;
    }
}
