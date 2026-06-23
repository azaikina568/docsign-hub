<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\PartyRole;
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

        $role = PartyRole::from($data['role'] ?? PartyRole::Signer->value);

        return DocumentParty::create([
            'document_id' => $document->id,
            // Участник идентифицируется по email; если в системе уже есть пользователь
            // с таким email — связываем, но аккаунт для подписания не обязателен.
            'user_id' => User::where('email', $data['email'])->value('id'),
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            // Порядок подписания актуален только для подписантов; у наблюдателей он null.
            // Без явного order — добавляем в конец очереди (max существующих + 1).
            'signing_order' => $role === PartyRole::Signer
                ? ($data['signing_order'] ?? $this->nextSigningOrder($document))
                : null,
            'status' => PartyStatus::Pending,
        ]);
    }

    private function nextSigningOrder(Document $document): int
    {
        return (int) $document->parties()->where('role', PartyRole::Signer)->max('signing_order') + 1;
    }
}
