<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_exp,
            'document_type' => $this->tipo_documento,
            'document_number' => $this->ci,
            'full_name' => $this->nombre,
            'phone' => $this->celular,
            'email' => $this->email ?: $this->com_email,
        ];
    }
}
