<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\CancelDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\CancelDocumentRequest;
use App\Http\Resources\DocumentResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('Documents', weight: 3)]
class CancelDocumentController extends Controller
{
    /**
     * Cancel a document that is not yet in a final state.
     */
    public function __invoke(CancelDocumentRequest $request, Document $document, CancelDocumentAction $action): DocumentResource
    {
        $this->authorize('manage', $document);

        $document = $action->execute($document, $request->user(), $request->validated('reason'));

        return DocumentResource::make($document->load('parties'));
    }
}
