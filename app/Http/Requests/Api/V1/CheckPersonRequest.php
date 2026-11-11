<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
            'numero_documento' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]{4,20}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pin' => strtoupper(trim((string) $this->input('pin'))),
            'numero_documento' => strtoupper(trim((string) $this->input('numero_documento'))),
        ]);
    }
}
