<?php

namespace App\Http\Resources;

use App\Domain\Users\Data\TokenPair;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenPairResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TokenPair $pair */
        $pair = $this->resource;

        return [
            'token_type' => 'Bearer',
            'access_token' => $pair->accessToken,
            'access_expires_at' => $pair->accessExpiresAt->toISOString(),
            'refresh_token' => $pair->refreshToken,
            'refresh_expires_at' => $pair->refreshExpiresAt->toISOString(),
        ];
    }
}
