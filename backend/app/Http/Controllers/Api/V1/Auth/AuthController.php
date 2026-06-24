<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Users\Actions\IssueTokenPairAction;
use App\Domain\Users\Actions\RefreshTokensAction;
use App\Domain\Users\Actions\RegisterUserAction;
use App\Domain\Users\Data\TokenPair;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\TokenPairResource;
use App\Http\Resources\UserResource;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Authentication', 'Регистрация, вход и управление токенами (access + refresh).', weight: 2)]
class AuthController extends Controller
{
    /**
     * Register a new account and issue an access + refresh token pair.
     */
    public function register(RegisterRequest $request, RegisterUserAction $action, IssueTokenPairAction $issueTokens): JsonResponse
    {
        $user = $action->execute($request->validated());

        return $this->tokenResponse($user, 'Registered.', $issueTokens->execute($user), 201);
    }

    /**
     * Log in with email and password and issue an access + refresh token pair.
     */
    public function login(LoginRequest $request, IssueTokenPairAction $issueTokens): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check((string) $request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return $this->tokenResponse($user, 'Logged in.', $issueTokens->execute($user));
    }

    /**
     * Exchange a refresh token for a new access + refresh pair (rotates the refresh token).
     */
    public function refresh(Request $request, RefreshTokensAction $action): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        abort_unless($current instanceof PersonalAccessToken, 401);

        return $this->tokenResponse($user, 'Token refreshed.', $action->execute($user, $current));
    }

    /**
     * Revoke the whole session — both the access and the refresh token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->family) {
            PersonalAccessToken::query()->where('family', $token->family)->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function tokenResponse(User $user, string $message, TokenPair $tokens, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'user' => new UserResource($user),
            'tokens' => new TokenPairResource($tokens),
        ], $status);
    }
}
