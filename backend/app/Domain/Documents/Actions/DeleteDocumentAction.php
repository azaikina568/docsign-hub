<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;

class DeleteDocumentAction
{
    public function execute(Document $document): void
    {
        if (! $document->status->isDraft()) {
            throw new DocumentStateException('Only draft documents can be deleted.');
        }

        $document->delete();
    }
}
