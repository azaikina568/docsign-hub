<?php

namespace App\Domain\Users\Data;

use Carbon\CarbonInterface;

class TokenPair
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly CarbonInterface $accessExpiresAt,
        public readonly CarbonInterface $refreshExpiresAt,
    ) {}
}
