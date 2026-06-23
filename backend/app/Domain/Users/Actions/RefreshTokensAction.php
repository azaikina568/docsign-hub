<?php

namespace App\Domain\Users\Actions;

use App\Domain\Users\Data\TokenPair;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;

class RefreshTokensAction
{
    public function __construct(private readonly IssueTokenPairAction $issuePair) {}

    /**
     * Меняет refresh на новую пару с ротацией. Если предъявлен уже ротированный
     * refresh (consumed) — это переиспользование (возможна кража): гасим всю семью.
     */
    public function execute(User $user, PersonalAccessToken $current): TokenPair
    {
        $family = $current->family;

        if ($current->consumed_at !== null) {
            PersonalAccessToken::query()->where('family', $family)->delete();

            throw new AuthenticationException('Refresh token has already been used.');
        }

        return DB::transaction(function () use ($user, $current, $family) {
            $current->forceFill(['consumed_at' => now()])->save();

            // Старый access этой семьи заменяем; consumed refresh оставляем для детекции переиспользования.
            PersonalAccessToken::query()
                ->where('family', $family)
                ->where('id', '!=', $current->id)
                ->whereNull('consumed_at')
                ->delete();

            return $this->issuePair->execute($user, $family);
        });
    }
}
