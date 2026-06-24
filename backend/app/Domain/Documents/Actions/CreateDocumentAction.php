<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentStatusHistory;
use App\Domain\Messaging\Data\OutboxEvent;
use App\Domain\Messaging\Enums\DomainEventType;
use App\Domain\Messaging\Services\OutboxWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDocumentAction
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    /**
     * @param  array{title: string, expires_at?: string|null}  $data
     */
    public function execute(User $owner, array $data): Document
    {
        return DB::transaction(function () use ($owner, $data) {
            $document = Document::create([
                'owner_id' => $owner->id,
                'title' => $data['title'],
                'status' => DocumentStatus::Draft,
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => null,
                'to_status' => DocumentStatus::Draft,
                'changed_by_user_id' => $owner->id,
            ]);

            $this->outbox->record(OutboxEvent::make(DomainEventType::DocumentCreated, $document->ulid, $owner->id, [
                'title' => $document->title,
                'status' => $document->status->value,
            ]));

            return $document;
        });
    }
}
