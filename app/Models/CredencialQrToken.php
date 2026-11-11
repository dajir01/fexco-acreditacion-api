<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialQrToken extends Model
{
    protected $table = 'credencial_qr_tokens';

    protected $fillable = [
        'id_credencial', 'token_hash', 'token_cifrado', 'version', 'activo',
        'revocado_at', 'motivo_revocacion',
    ];

    protected function casts(): array
    {
        return [
            'id_credencial' => 'integer',
            'version' => 'integer',
            'activo' => 'boolean',
            'revocado_at' => 'datetime',
        ];
    }
}
