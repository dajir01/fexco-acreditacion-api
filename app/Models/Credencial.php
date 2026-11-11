<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credencial extends Model
{
    protected $table = 'credenciales';

    protected $primaryKey = 'id_credencial';

    protected $fillable = [
        'id_empresa', 'id_feria', 'id_contrato', 'id_expositor', 'tipo_credencial',
        'modalidad_credencial', 'estado_credencial', 'cod_credencial', 'com_medio',
        'com_dias', 'ingreso',
    ];

    public function expositor(): BelongsTo
    {
        return $this->belongsTo(Expositor::class, 'id_expositor', 'id_exp');
    }

    public function feria(): BelongsTo
    {
        return $this->belongsTo(Feria::class, 'id_feria', 'id_feria');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(CredencialQrToken::class, 'id_credencial', 'id_credencial');
    }
}
