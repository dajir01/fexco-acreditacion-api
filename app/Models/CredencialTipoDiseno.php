<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialTipoDiseno extends Model
{
    protected $table = 'credencial_tipo_diseno';

    public $timestamps = false;

    protected $fillable = [
        'id_feria', 'tipo_credencial', 'ruta', 'nombre_archivo', 'mime', 'ancho', 'alto',
    ];
}
