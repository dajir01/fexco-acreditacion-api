<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['pin' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['pin' => strtoupper(trim((string) $this->input('pin')))]);
    }
}
