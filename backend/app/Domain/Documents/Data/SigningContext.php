<?php

namespace App\Domain\Documents\Data;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Models\SignatureToken;

/**
 * Контекст подписания по capability-токену: документ, конкретное участие и
 * вычисленные флаги для публичной страницы. Чужих участников не раскрываем —
 * только агрегированный прогресс.
 */
final class SigningContext
{
    public function __construct(
        public readonly Document $document,
        public readonly DocumentParty $party,
        public readonly SignatureToken $token,
        public readonly int $signedSigners,
        public readonly int $totalSigners,
        public readonly bool $isTheirTurn,
    ) {}

    public function requiresAccount(): bool
    {
        return $this->party->user_id !== null;
    }

    public function alreadySigned(): bool
    {
        return $this->party->status === PartyStatus::Signed;
    }

    public function isExpired(): bool
    {
        return (bool) $this->document->expires_at?->isPast();
    }

    public function isDocumentOpen(): bool
    {
        return in_array($this->document->status, [DocumentStatus::Pending, DocumentStatus::PartiallySigned], true);
    }

    /**
     * Можно ли подписать прямо по ссылке (без логина): актуально для внешнего участника.
     * Account-bound увидит requires_account и пойдёт через логин.
     */
    public function canSignViaLink(): bool
    {
        return $this->isDocumentOpen()
            && ! $this->isExpired()
            && ! $this->alreadySigned()
            && $this->isTheirTurn
            && ! $this->requiresAccount();
    }
}
