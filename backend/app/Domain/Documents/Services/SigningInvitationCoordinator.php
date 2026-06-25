<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Actions\DeliverSigningInvitationAction;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;

/**
 * Поэтапная рассылка приглашений (OPEN_QUESTIONS Q3). Подписание строго последовательное, поэтому
 * приглашение получает только подписант, чья очередь сейчас — первый при send, следующий после каждой
 * подписи. Один метод закрывает оба случая: «текущий по очереди» = самый ранний ещё не подписавший signer.
 */
class SigningInvitationCoordinator
{
    public function __construct(private readonly DeliverSigningInvitationAction $deliver) {}

    public function inviteCurrentSigner(Document $document): void
    {
        // Приглашаем только пока документ открыт: завершённый/отменённый/истёкший — некого звать.
        if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::PartiallySigned], true)) {
            return;
        }

        $current = $this->currentSigner($document);

        // Идемпотентность приглашения: уже позванному участнику повторно не шлём. Защищает от двойного
        // письма, когда на одного и того же текущего подписанта указали несколько событий (sent + signed,
        // либо account-bound подписал из дашборда раньше, чем обработался sent) или при повторной доставке.
        if ($current !== null && $current->invited_at === null) {
            $this->deliver->execute($document, $current);
        }
    }

    /**
     * Текущий по очереди подписант: самый ранний signer со статусом != signed.
     * Последовательность гарантирует, что все более ранние уже подписали.
     */
    private function currentSigner(Document $document): ?DocumentParty
    {
        return $document->parties()
            ->where('role', PartyRole::Signer)
            ->where('status', '!=', PartyStatus::Signed->value)
            ->orderBy('signing_order')
            ->first();
    }
}
