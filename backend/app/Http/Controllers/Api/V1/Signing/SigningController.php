<?php

namespace App\Http\Controllers\Api\V1\Signing;

use App\Domain\Documents\Actions\GetSigningContextAction;
use App\Domain\Documents\Actions\SignDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SignatureResource;
use App\Http\Resources\SigningContextResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Signing', 'Подписание участником — по одноразовой ссылке (capability) или под своим аккаунтом (identity).', weight: 4)]
class SigningController extends Controller
{
    /**
     * Get the signing context for a capability token.
     *
     * Публичный доступ по одноразовой ссылке: только своё участие и общий прогресс.
     */
    public function show(string $token, GetSigningContextAction $action): SigningContextResource
    {
        return SigningContextResource::make($action->execute($token));
    }

    /**
     * Sign the document using a capability token.
     *
     * Для внешнего участника достаточно токена; для account-bound требуется логин как этот пользователь.
     */
    public function sign(Request $request, string $token, GetSigningContextAction $resolve, SignDocumentAction $sign): JsonResponse
    {
        $context = $resolve->execute($token);

        $signature = $sign->execute(
            $context->party,
            // Bearer-токен резолвится опционально: гость подписывает по capability, account-bound — по identity.
            $request->user('sanctum'),
            (string) $request->ip(),
            $request->userAgent(),
            $context->token,
        );

        return SignatureResource::make($signature)
            ->response()
            ->setStatusCode($signature->wasRecentlyCreated ? 201 : 200);
    }
}
