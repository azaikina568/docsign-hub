<?php

namespace App\Http\Requests\Documents;

use App\Domain\Documents\Enums\PartyRole;
use App\Domain\Documents\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentPartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // Один email — один участник в пределах документа (без дублей → 422, не 500).
                Rule::unique('document_parties', 'email')->where('document_id', $document->id),
            ],
            'role' => ['nullable', Rule::enum(PartyRole::class)],
            'signing_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
