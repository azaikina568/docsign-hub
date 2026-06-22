<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\CreateDocumentAction;
use App\Domain\Documents\Actions\DeleteDocumentAction;
use App\Domain\Documents\Actions\UpdateDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentStatusHistoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Documents
 */
class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = $request->user()->documents()
            ->withCount('parties')
            ->latest()
            ->paginate(15);

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request, CreateDocumentAction $action): JsonResponse
    {
        $document = $action->execute($request->user(), $request->validated());

        return DocumentResource::make($document)->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return DocumentResource::make($document->load('parties'));
    }

    public function update(UpdateDocumentRequest $request, Document $document, UpdateDocumentAction $action): DocumentResource
    {
        $this->authorize('update', $document);

        $document = $action->execute($document, $request->validated());

        return DocumentResource::make($document->load('parties'));
    }

    public function destroy(Document $document, DeleteDocumentAction $action): JsonResponse
    {
        $this->authorize('delete', $document);

        $action->execute($document);

        return response()->json(['message' => 'Document deleted.']);
    }

    public function events(Document $document): AnonymousResourceCollection
    {
        $this->authorize('view', $document);

        return DocumentStatusHistoryResource::collection(
            $document->statusHistory()->latest('id')->get(),
        );
    }
}
