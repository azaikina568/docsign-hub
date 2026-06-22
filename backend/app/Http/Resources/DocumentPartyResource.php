<?php

namespace App\Http\Resources;

use App\Domain\Documents\Models\DocumentParty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentParty
 */
class DocumentPartyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'signing_order' => $this->signing_order,
            'status' => $this->status->value,
            'signed_at' => $this->signed_at?->toISOString(),
        ];
    }
}
