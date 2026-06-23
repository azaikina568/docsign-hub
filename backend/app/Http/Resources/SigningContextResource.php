<?php

namespace App\Http\Resources;

use App\Domain\Documents\Data\SigningContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичное представление контекста подписания. Чужих участников не раскрываем —
 * только данные своего участия и агрегированный прогресс.
 *
 * @property SigningContext $resource
 */
class SigningContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->resource;
        $document = $context->document;
        $party = $context->party;

        return [
            'document' => [
                'id' => $document->ulid,
                'title' => $document->title,
                'status' => $document->status->value,
                'expires_at' => $document->expires_at?->toISOString(),
            ],
            'party' => [
                'name' => $party->name,
                'email' => $party->email,
                'role' => $party->role->value,
                'signing_order' => $party->signing_order,
                'status' => $party->status->value,
                'signed_at' => $party->signed_at?->toISOString(),
            ],
            'progress' => [
                'signed' => $context->signedSigners,
                'total' => $context->totalSigners,
            ],
            'requires_account' => $context->requiresAccount(),
            'already_signed' => $context->alreadySigned(),
            'expired' => $context->isExpired(),
            'your_turn' => $context->isTheirTurn,
            'can_sign' => $context->canSignViaLink(),
        ];
    }
}
