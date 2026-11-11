<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OperatorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'dispositivo_ref' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }
}
