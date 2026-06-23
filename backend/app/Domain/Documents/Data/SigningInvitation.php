<?php

namespace App\Domain\Documents\Data;

use App\Domain\Documents\Models\DocumentParty;

/**
 * Приглашение на подписание: участник + одноразовый plain-токен.
 * Живёт только в памяти при отправке документа — plain-токен нигде не хранится.
 */
final class SigningInvitation
{
    public function __construct(
        public readonly DocumentParty $party,
        public readonly string $plainToken,
    ) {}
}
