<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Data\TokenPair;
use App\Models\User;
use Illuminate\Support\Str;

class IssueTokenPairAction
{
    /**
     * Выдаёт пару токенов одной семьи: короткий access (ability access-api)
     * и долгоживущий refresh (ability issue-access). Ротация в RefreshTokensAction
     * сохраняет ту же семью, поэтому отзыв семьи гасит всю сессию сразу.
     */
    public function execute(User $user, ?string $family = null): TokenPair
    {
        $family ??= (string) Str::uuid();

        $accessExpiresAt = now()->addMinutes((int) config('sanctum.access_token_expiration'));
        $refreshExpiresAt = now()->addMinutes((int) config('sanctum.refresh_token_expiration'));

        $access = $user->createToken('access', ['access-api'], $accessExpiresAt);
        $access->accessToken->family = $family;
        $access->accessToken->save();

        $refresh = $user->createToken('refresh', ['issue-access'], $refreshExpiresAt);
        $refresh->accessToken->family = $family;
        $refresh->accessToken->save();

        return new TokenPair(
            $access->plainTextToken,
            $refresh->plainTextToken,
            $accessExpiresAt,
            $refreshExpiresAt,
        );
    }
}
