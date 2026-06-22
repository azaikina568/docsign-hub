<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;

class RemoveDocumentPartyAction
{
    public function execute(Document $document, DocumentParty $party): void
    {
        if (! $document->status->isDraft()) {
            throw new DocumentStateException('Parties can only be changed while the document is a draft.');
        }

        $party->delete();
    }
}
