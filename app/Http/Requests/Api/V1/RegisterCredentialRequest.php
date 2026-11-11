<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
            'tipo_documento' => [
                'required',
                'string',
                Rule::in(['Cédula de identidad', 'Pasaporte', 'Licencia de conducir', 'Otro']),
            ],
            'numero_documento' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]{4,20}$/'],
            'nombre_completo' => ['required', 'string', 'max:255', 'regex:/^[\p{L}][\p{L}\s.\'-]{2,254}$/u'],
            'telefono' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'correo' => ['required', 'email:rfc', 'max:255'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pin' => strtoupper(trim((string) $this->input('pin'))),
            'numero_documento' => strtoupper(trim((string) $this->input('numero_documento'))),
            'nombre_completo' => trim((string) $this->input('nombre_completo')),
            'telefono' => trim((string) $this->input('telefono')),
            'correo' => strtolower(trim((string) $this->input('correo'))),
        ]);
    }
}
