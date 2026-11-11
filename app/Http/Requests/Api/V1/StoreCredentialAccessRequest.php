<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:200'],
            'idempotency_key' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El código QR es obligatorio.',
            'idempotency_key.required' => 'La clave de operación es obligatoria.',
            'idempotency_key.regex' => 'La clave de operación tiene un formato inválido.',
        ];
    }
}
