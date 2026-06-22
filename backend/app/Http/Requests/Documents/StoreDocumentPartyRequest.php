<?php

namespace App\Http\Requests\Documents;

use App\Domain\Documents\Enums\PartyRole;
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['nullable', Rule::enum(PartyRole::class)],
            'signing_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
