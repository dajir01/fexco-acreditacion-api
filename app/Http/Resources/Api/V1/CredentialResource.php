<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_credencial,
            'code' => $this->cod_credencial,
            'event_id' => $this->id_feria,
            'company_id' => $this->id_empresa,
            'credential_type' => $this->tipo_credencial,
            'modality' => (int) $this->modalidad_credencial === 0 ? 'physical' : 'digital',
            'downloadable' => (int) $this->modalidad_credencial !== 0,
            'delivery_message' => (int) $this->modalidad_credencial === 0
                ? 'Tu credencial física puede pasar a recogerse por las oficinas o el punto de acreditación.'
                : null,
            'status' => match ((int) $this->estado_credencial) {
                1 => 'active',
                2 => 'cancelled',
                3 => 'deleted',
                default => 'disabled',
            },
            'person' => new PersonResource($this->whenLoaded('expositor')),
        ];
    }
}
