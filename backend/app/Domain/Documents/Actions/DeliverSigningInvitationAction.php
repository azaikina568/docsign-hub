<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Contracts\SigningInvitationNotifier;
use App\Domain\Documents\Data\SigningInvitation;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use Illuminate\Support\Str;

/**
 * Доставляет приглашение конкретному подписанту. Plain-токен нигде не хранится, поэтому к моменту
 * отложенной доставки (из consumer'а) его уже нет — мы перевыпускаем токен прямо здесь: генерируем
 * свежий plain, перезаписываем хеш и срок. Так emailed-ссылка всегда одноразовая и актуальная,
 * а в БД по-прежнему лежит только хеш.
 */
class DeliverSigningInvitationAction
{
    public function __construct(private readonly SigningInvitationNotifier $notifier) {}

    public function execute(Document $document, DocumentParty $party): void
    {
        $plain = Str::random(40);

        $token = $party->signatureToken()->firstOrNew([]);
        $token->forceFill([
            'document_party_id' => $party->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $document->expires_at,
            'used_at' => null,
        ])->save();

        $this->notifier->notify($document, new SigningInvitation($party, $plain));

        // Отмечаем доставку только после успешной отправки: если notify бросит, invited_at останется
        // null и повторная обработка (реплей из DLQ) пошлёт приглашение заново.
        $party->forceFill(['invited_at' => now()])->save();
    }
}
