<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\AddDocumentPartyAction;
use App\Domain\Documents\Actions\RemoveDocumentPartyAction;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentParty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentPartyRequest;
use App\Http\Resources\DocumentPartyResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Documents
 */
class DocumentPartyController extends Controller
{
    public function store(StoreDocumentPartyRequest $request, Document $document, AddDocumentPartyAction $action): JsonResponse
    {
        $this->authorize('manage', $document);

        $party = $action->execute($document, $request->validated());

        return DocumentPartyResource::make($party)->response()->setStatusCode(201);
    }

    public function destroy(Document $document, DocumentParty $party, RemoveDocumentPartyAction $action): JsonResponse
    {
        $this->authorize('manage', $document);

        $action->execute($document, $party);

        return response()->json(['message' => 'Party removed.']);
    }
}
