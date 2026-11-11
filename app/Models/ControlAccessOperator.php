<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ControlAccessOperator extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios_control';

    protected $primaryKey = 'id_usuario_control';

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
