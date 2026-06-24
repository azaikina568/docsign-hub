<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Exceptions\DocumentStateException;
use App\Domain\Documents\Exceptions\SigningForbiddenException;
use App\Domain\Documents\Exceptions\SigningWindowClosedException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Domain\Documents\Models\Signature;
use App\Domain\Documents\Models\SignatureToken;
use App\Domain\Documents\Services\DocumentStatusService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignDocumentAction
{
    public function __construct(private readonly DocumentStatusService $statusService) {}

    /**
     * Подписывает участие party. Идемпотентно: повторная подпись возвращает уже созданную.
     * Объединяет оба сценария — внешний по capability-токену и зарегистрированный по identity.
     *
     * @param  User|null  $authUser  залогиненный пользователь (для account-bound обязателен)
     * @param  SignatureToken|null  $token  capability-токен (для внешнего обязателен)
     */
    public function execute(
        DocumentParty $party,
        ?User $authUser,
        string $ip,
        ?string $userAgent,
        ?SignatureToken $token = null,
    ): Signature {
        if ($party->role !== PartyRole::Signer) {
            throw new SigningForbiddenException('Only signers can sign this document.');
        }

        return DB::transaction(function () use ($party, $authUser, $ip, $userAgent, $token): Signature {
            // Блокируем документ на время подписания — защита от гонки параллельных подписей
            // (две одновременные подписи могли бы оба раза увидеть «не все подписали»).
            $document = Document::query()->whereKey($party->document_id)->lockForUpdate()->firstOrFail();

            // Авторизация актёра — раньше любых проверок состояния документа, чтобы посторонний
            // всегда получал 403 и не мог по кодам ответа узнать стадию жизненного цикла документа.
            $this->assertActorMaySign($party, $authUser, $token);

            // Идемпотентность: повторная подпись возвращает существующую, даже если документ
            // уже целиком signed (иначе single-signer упёрся бы в «не открыт»).
            if ($party->status === PartyStatus::Signed) {
                return $party->signature()->firstOrFail();
            }

            if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::PartiallySigned], true)) {
                throw new DocumentStateException('Document is not open for signing.');
            }

            if ($document->expires_at?->isPast()) {
                throw new SigningWindowClosedException('The signing deadline has passed.');
            }

            // Свежесть токена проверяем уже после идемпотентности: «использован» легитимен только
            // при повторной подписи, которую мы вернули выше.
            if ($token !== null && $token->used_at !== null) {
                throw new DocumentStateException('This signing link has already been used.');
            }

            $this->assertItIsPartyTurn($document, $party);

            $signature = $this->recordSignature($document, $party, $ip, $userAgent);

            $party->forceFill(['status' => PartyStatus::Signed, 'signed_at' => $signature->signed_at])->save();

            // Гасим capability-токен: emailed-ссылка одноразовая. Если подписали по identity
            // (token не передан) — закрываем висящий неиспользованный токен этого участника.
            $usedToken = $token ?? $party->signatureToken()->whereNull('used_at')->first();
            $usedToken?->forceFill(['used_at' => $signature->signed_at])->save();

            $this->advanceDocumentStatus($document, $authUser);

            return $signature;
        });
    }

    private function assertActorMaySign(DocumentParty $party, ?User $authUser, ?SignatureToken $token): void
    {
        if ($party->user_id !== null) {
            // Account-bound: одного capability-токена мало — нужна identity именно этого участника.
            if ($authUser === null || $authUser->id !== $party->user_id) {
                throw new SigningForbiddenException('Sign in as the invited account to sign this document.');
            }

            return;
        }

        // Внешний участник: подпись разрешает только владение capability-токеном.
        if ($token === null) {
            throw new SigningForbiddenException('A valid signing link is required.');
        }
    }

    private function assertItIsPartyTurn(Document $document, DocumentParty $party): void
    {
        $blocked = $document->parties()
            ->where('role', PartyRole::Signer)
            ->where('signing_order', '<', $party->signing_order)
            ->where('status', '!=', PartyStatus::Signed->value)
            ->exists();

        if ($blocked) {
            throw new DocumentStateException('Earlier signers must sign first.');
        }
    }

    private function recordSignature(Document $document, DocumentParty $party, string $ip, ?string $userAgent): Signature
    {
        $signedAt = now();
        $nonce = Str::random(32);

        // Демонстрационная подпись: привязка к снимку содержимого (content_hash, снят при send),
        // участнику и моменту. Реальной криптографии/ЭЦП здесь нет (см. SIGNING_SECURITY.md).
        $signatureHash = hash('sha256', implode('|', [
            (string) $document->content_hash,
            $party->id,
            $signedAt->toISOString(),
            $nonce,
        ]));

        return Signature::create([
            'document_id' => $document->id,
            'document_party_id' => $party->id,
            'signature_hash' => $signatureHash,
            'signed_payload' => [
                'document_ulid' => $document->ulid,
                'title' => $document->title,
                'content_hash' => $document->content_hash,
                'party_email' => $party->email,
                'signing_order' => $party->signing_order,
                'nonce' => $nonce,
            ],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'signed_at' => $signedAt,
        ]);
    }

    private function advanceDocumentStatus(Document $document, ?User $actor): void
    {
        $signers = $document->parties()->where('role', PartyRole::Signer);
        $total = $signers->count();
        $signed = (clone $signers)->where('status', PartyStatus::Signed->value)->count();

        if ($signed >= $total) {
            $this->statusService->transition($document, DocumentStatus::Signed, $actor, 'All signers have signed.');
        } elseif ($document->status === DocumentStatus::Pending) {
            $this->statusService->transition($document, DocumentStatus::PartiallySigned, $actor, 'A signer has signed.');
        }
    }
}
