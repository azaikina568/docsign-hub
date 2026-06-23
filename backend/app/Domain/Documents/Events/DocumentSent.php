<?php

namespace App\Domain\Documents\Events;

use App\Domain\Documents\Data\SigningInvitation;
use App\Domain\Documents\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Документ отправлен на подписание. Точка расширения: на это событие вешаются
 * рассылка приглашений, а в будущем — запись в outbox и audit (Этап 5).
 */
class DocumentSent
{
    use Dispatchable;

    /**
     * @param  list<SigningInvitation>  $invitations
     */
    public function __construct(
        public readonly Document $document,
        public readonly array $invitations,
    ) {}
}
