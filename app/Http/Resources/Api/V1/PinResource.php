<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PinResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->estado === 'usado' ? 'used' : 'available',
            'event' => [
                'id' => $this->id_feria,
                'name' => $this->feria?->nombre_feria,
            ],
            'company' => [
                'id' => $this->id_empresa,
                'name' => $this->empresa?->nombre_empresa,
            ],
            'credential_type' => $this->tipo_credencial,
            'modality' => (int) $this->modalidad_credencial === 0 ? 'physical' : 'digital',
        ];
    }
}
