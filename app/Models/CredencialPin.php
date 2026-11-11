<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredencialPin extends Model
{
    protected $table = 'credencial_pines';

    protected $fillable = [
        'id_feria', 'id_empresa', 'id_contrato', 'id_empresa_evento_credencial',
        'tipo_credencial', 'modalidad_credencial', 'pin_hash', 'estado',
        'id_credencial', 'nombre_destinatario', 'celular_destinatario',
        'email_destinatario', 'usado_at',
    ];

    protected function casts(): array
    {
        return [
            'id_feria' => 'integer', 'id_empresa' => 'integer', 'id_contrato' => 'integer',
            'id_credencial' => 'integer', 'modalidad_credencial' => 'integer',
            'usado_at' => 'datetime',
        ];
    }

    public function feria(): BelongsTo
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function credencial(): BelongsTo
    {
        return $this->belongsTo(Credencial::class, 'id_credencial', 'id_credencial');
    }
}
