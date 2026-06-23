<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Data\SigningContext;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Models\SignatureToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetSigningContextAction
{
    /**
     * Разворачивает capability-токен в контекст подписания. Токен ищем по sha256-хешу;
     * неизвестный токен неотличим от любого мусора в URL → 404.
     */
    public function execute(string $plainToken): SigningContext
    {
        $token = SignatureToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->with(['party.document.parties'])
            ->first();

        if ($token === null || $token->party === null) {
            throw new NotFoundHttpException('Signing link not found.');
        }

        $party = $token->party;
        $document = $party->document;

        $signers = $document->parties->where('role', PartyRole::Signer);

        // Очередь соблюдена, если все подписанты с меньшим order уже подписали.
        $isTheirTurn = $signers
            ->where('signing_order', '<', $party->signing_order)
            ->every(fn (DocumentParty $earlier) => $earlier->status === PartyStatus::Signed);

        return new SigningContext(
            document: $document,
            party: $party,
            token: $token,
            signedSigners: $signers->where('status', PartyStatus::Signed)->count(),
            totalSigners: $signers->count(),
            isTheirTurn: $isTheirTurn,
        );
    }
}
