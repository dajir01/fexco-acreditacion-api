<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expositor extends Model
{
    protected $table = 'expositores';

    protected $primaryKey = 'id_exp';

    protected $fillable = [
        'ci', 'tipo_documento', 'nombre', 'email', 'celular', 'foto', 'estado',
    ];

    public function credenciales(): HasMany
    {
        return $this->hasMany(Credencial::class, 'id_expositor', 'id_exp');
    }
}
