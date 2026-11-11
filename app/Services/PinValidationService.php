<?php

namespace App\Services;

use App\Models\CredencialPin;
use Illuminate\Database\Eloquent\Builder;

class PinValidationService
{
    /**
     * @return Builder<CredencialPin>
     */
    public function queryByValue(string $value): Builder
    {
        return CredencialPin::query()
            ->where('pin_hash', hash('sha256', strtoupper(trim($value))));
    }

    public function findByValue(string $value): ?CredencialPin
    {
        $pin = $this->queryByValue($value)->first();
        if ($pin && $this->isUsable($pin)) {
            $pin->load(['feria', 'empresa']);
        }

        return $pin;
    }

    public function isUsable(CredencialPin $pin): bool
    {
        return in_array($pin->estado, ['disponible', 'enviado'], true);
    }
}
