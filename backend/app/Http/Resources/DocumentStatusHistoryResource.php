<?php

namespace App\Http\Resources;

use App\Domain\Documents\Models\DocumentStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentStatusHistory
 */
class DocumentStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'reason' => $this->reason,
            'changed_by_user_id' => $this->changed_by_user_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
