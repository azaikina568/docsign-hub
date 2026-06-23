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

    /**
     * Разрешён ли переход в указанный статус. Единая карта переходов —
     * чтобы статус нельзя было «перепрыгнуть» в обход бизнес-правил.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::PartiallySigned, self::Signed, self::Cancelled, self::Expired],
            self::PartiallySigned => [self::Signed, self::Cancelled, self::Expired],
            self::Signed, self::Cancelled, self::Expired => [],
        };
    }
}
