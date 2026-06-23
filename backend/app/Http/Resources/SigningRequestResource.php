<?php

namespace App\Http\Resources;

use App\Domain\Documents\Models\DocumentParty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pending-участие текущего пользователя для дашборда: что и в каком документе ему подписать.
 *
 * @mixin DocumentParty
 */
class SigningRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'party_id' => $this->id,
            'signing_order' => $this->signing_order,
            'status' => $this->status->value,
            'document' => [
                'id' => $this->document->ulid,
                'title' => $this->document->title,
                'status' => $this->document->status->value,
                'expires_at' => $this->document->expires_at?->toISOString(),
            ],
        ];
    }
}
