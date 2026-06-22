<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;

class AddDocumentPartyAction
{
    /**
     * @param  array{name: string, email: string, role?: string, signing_order?: int}  $data
     */
    public function execute(Document $document, array $data): DocumentParty
    {
        if (! $document->status->isDraft()) {
            throw new DocumentStateException('Parties can only be changed while the document is a draft.');
        }

        /** @var DocumentParty $party */
        $party = $document->parties()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'signer',
            'signing_order' => $data['signing_order'] ?? 1,
            'status' => PartyStatus::Pending,
        ]);

        return $party;
    }
}
