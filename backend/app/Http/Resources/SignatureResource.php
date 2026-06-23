<?php

namespace App\Http\Resources;

use App\Domain\Documents\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Signature
 */
class SignatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'party_id' => $this->document_party_id,
            'signature_hash' => $this->signature_hash,
            'signed_at' => $this->signed_at->toISOString(),
        ];
    }
}
