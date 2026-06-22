<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use Illuminate\Support\Arr;

class UpdateDocumentAction
{
    /**
     * @param  array{title?: string, expires_at?: string|null}  $data
     */
    public function execute(Document $document, array $data): Document
    {
        if (! $document->status->isDraft()) {
            throw new DocumentStateException('Only draft documents can be edited.');
        }

        $document->fill(Arr::only($data, ['title', 'expires_at']));
        $document->save();

        return $document;
    }
}
