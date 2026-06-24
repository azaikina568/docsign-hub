<?php

namespace App\Http\Controllers\Api\V1\Signing;

use App\Domain\Documents\Actions\SignDocumentAction;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Enums\PartyStatus;
use App\Domain\Documents\Models\DocumentParty;
use App\Http\Controllers\Controller;
use App\Http\Resources\SignatureResource;
use App\Http\Resources\SigningRequestResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Signing', weight: 4)]
class SigningRequestController extends Controller
{
    /**
     * List documents awaiting my signature.
     *
     * Pending-участия текущего пользователя (account-bound сценарий) — подписать из дашборда.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $parties = DocumentParty::query()
            ->where('user_id', $request->user()->id)
            ->where('role', PartyRole::Signer)
            ->where('status', PartyStatus::Pending)
            ->whereHas('document', fn ($query) => $query->whereIn('status', [
                DocumentStatus::Pending->value,
                DocumentStatus::PartiallySigned->value,
            ]))
            ->with('document')
            ->paginate(15);

        return SigningRequestResource::collection($parties);
    }

    /**
     * Sign a document as the authenticated invited user.
     *
     * Identity-путь: подпись разрешена, только если party.user_id совпадает с залогиненным.
     */
    public function sign(Request $request, DocumentParty $party, SignDocumentAction $sign): JsonResponse
    {
        $signature = $sign->execute(
            $party,
            $request->user(),
            (string) $request->ip(),
            $request->userAgent(),
        );

        return SignatureResource::make($signature)
            ->response()
            ->setStatusCode($signature->wasRecentlyCreated ? 201 : 200);
    }
}
