<?php

namespace App\Http\Resources;

use App\Domain\Documents\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'parties_count' => $this->whenCounted('parties'),
            'parties' => DocumentPartyResource::collection($this->whenLoaded('parties')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
