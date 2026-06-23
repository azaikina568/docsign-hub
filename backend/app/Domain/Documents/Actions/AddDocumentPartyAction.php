<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Models\User;

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
            // Участник идентифицируется по email; если в системе уже есть пользователь
            // с таким email — связываем, но аккаунт для подписания не обязателен.
            'user_id' => User::where('email', $data['email'])->value('id'),
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'signer',
            'signing_order' => $data['signing_order'] ?? 1,
            'status' => PartyStatus::Pending,
        ]);

        return $party;
    }
}
