<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Actions\SendDocumentAction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Documents', weight: 3)]
class SendDocumentController extends Controller
{
    /**
     * Send a draft document for signing.
     *
     * Подписантам уходят персональные ссылки на их email; отправителю токены не возвращаются.
     */
    public function __invoke(Request $request, Document $document, SendDocumentAction $action): DocumentResource
    {
        $this->authorize('manage', $document);

        $action->execute($document, $request->user());

        return DocumentResource::make($document->load('parties'));
    }
}
