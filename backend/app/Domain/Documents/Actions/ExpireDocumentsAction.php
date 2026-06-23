<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentStatusService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ExpireDocumentsAction
{
    public function __construct(private readonly DocumentStatusService $statusService) {}

    /**
     * Переводит просроченные документы (в процессе подписания) в статус expired.
     * Каждый документ — в своей транзакции: сбой одного не откатывает остальные.
     */
    public function execute(?CarbonInterface $now = null): int
    {
        $now ??= now();

        $documents = Document::query()
            ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::PartiallySigned])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($documents as $document) {
            DB::transaction(function () use ($document): void {
                $this->statusService->transition($document, DocumentStatus::Expired, null, 'Document expired.');
            });
        }

        return $documents->count();
    }
}
