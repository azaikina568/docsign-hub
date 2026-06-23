<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ExtendDeadlineRequest extends FormRequest
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
            // Только вперёд и только в будущее; «не позже текущего» проверяет действие (знает дедлайн документа).
            'expires_at' => ['required', 'date', 'after:now'],
        ];
    }
}
