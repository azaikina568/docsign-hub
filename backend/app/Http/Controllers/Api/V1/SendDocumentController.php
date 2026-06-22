<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\SendDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Documents
 */
class SendDocumentController extends Controller
{
    /**
     * Send a draft document for signing.
     *
     * Возвращает документ и одноразовые signing-токены участников (`signing_tokens`)
     * для demo-уведомления — больше они не отдаются.
     */
    public function __invoke(Request $request, Document $document, SendDocumentAction $action): JsonResponse
    {
        $this->authorize('manage', $document);

        $tokens = $action->execute($document, $request->user());

        return DocumentResource::make($document->load('parties'))
            ->additional(['signing_tokens' => $tokens])
            ->response();
    }
}
