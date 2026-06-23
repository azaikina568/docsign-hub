<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\ExtendDocumentDeadlineAction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ExtendDeadlineRequest;
use App\Http\Resources\DocumentResource;
use Illuminate\Support\Carbon;

/**
 * @group Documents
 */
class ExtendDeadlineController extends Controller
{
    /**
     * Extend the signing deadline of a document awaiting signatures.
     *
     * Двигает expires_at вперёд и продлевает срок ещё не использованных signing-токенов.
     */
    public function __invoke(ExtendDeadlineRequest $request, Document $document, ExtendDocumentDeadlineAction $action): DocumentResource
    {
        $this->authorize('manage', $document);

        $document = $action->execute(
            $document,
            $request->user(),
            Carbon::parse($request->validated('expires_at')),
        );

        return DocumentResource::make($document->load('parties'));
    }
}
