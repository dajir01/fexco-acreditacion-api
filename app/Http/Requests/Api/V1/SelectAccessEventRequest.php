<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SelectAccessEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
            'numero_puerta' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_feria.required' => 'El evento es obligatorio.',
            'id_feria.exists' => 'El evento seleccionado no existe.',
            'numero_puerta.required' => 'La puerta es obligatoria.',
        ];
    }
}
