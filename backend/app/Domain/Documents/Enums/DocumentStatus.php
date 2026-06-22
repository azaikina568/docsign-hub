<?php

namespace App\Domain\Documents\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case PartiallySigned = 'partially_signed';
    case Signed = 'signed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Документ ещё в черновике: можно менять содержимое и участников.
     */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Финальный статус: подписи больше не принимаются, изменения запрещены.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Signed, self::Cancelled, self::Expired], true);
    }

    /**
     * Документ можно отменить (только пока он не в финальном статусе).
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isFinal();
    }
}
