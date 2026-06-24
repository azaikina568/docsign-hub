<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\CreateDocumentAction;
use App\Domain\Documents\Actions\DeleteDocumentAction;
use App\Domain\Documents\Actions\UpdateDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\IndexDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentStatusHistoryResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Documents', 'Управление документами владельцем: черновики, участники, отправка, отмена, дедлайн, история.', weight: 3)]
class DocumentController extends Controller
{
    /**
     * List the authenticated owner's documents (paginated; optional ?status= filter).
     */
    public function index(IndexDocumentRequest $request): AnonymousResourceCollection
    {
        $documents = $request->user()->documents()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->withCount('parties')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return DocumentResource::collection($documents);
    }

    /**
     * Create a new draft document.
     */
    public function store(StoreDocumentRequest $request, CreateDocumentAction $action): JsonResponse
    {
        $document = $action->execute($request->user(), $request->validated());

        return DocumentResource::make($document)->response()->setStatusCode(201);
    }

    /**
     * Show a single document with its parties.
     */
    public function show(Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return DocumentResource::make($document->load('parties'));
    }

    /**
     * Update a draft document (only drafts are editable).
     */
    public function update(UpdateDocumentRequest $request, Document $document, UpdateDocumentAction $action): DocumentResource
    {
        $this->authorize('update', $document);

        $document = $action->execute($document, $request->validated());

        return DocumentResource::make($document->load('parties'));
    }

    /**
     * Delete a draft document (only drafts can be deleted).
     */
    public function destroy(Document $document, DeleteDocumentAction $action): JsonResponse
    {
        $this->authorize('delete', $document);

        $action->execute($document);

        return response()->json(['message' => 'Document deleted.']);
    }

    /**
     * Document status history (paginated; pass ?sort=asc for chronological order).
     */
    public function events(Request $request, Document $document): AnonymousResourceCollection
    {
        $this->authorize('view', $document);

        // По умолчанию свежие события сверху; ?sort=asc — в хронологическом порядке.
        $direction = $request->query('sort') === 'asc' ? 'asc' : 'desc';

        return DocumentStatusHistoryResource::collection(
            $document->statusHistory()->orderBy('id', $direction)->paginate(15),
        );
    }
}
