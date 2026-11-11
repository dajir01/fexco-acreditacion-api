<?php

namespace App\Services;

use App\Models\CredencialPin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CredentialAccessTokenService
{
    /**
     * @return array{pin_hash: string, credential_id: ?int}
     */
    public function issue(CredencialPin $pin): array
    {
        $token = Str::random(64);
        $context = [
            'pin_hash' => $pin->pin_hash,
            'credential_id' => $pin->id_credencial,
        ];

        Cache::put($this->key($token), $context, now()->addMinutes(
            config('rate_limiting.credential_access_token.ttl_minutes'),
        ));

        return [
            'token' => $token,
            'expires_in' => config('rate_limiting.credential_access_token.ttl_minutes') * 60,
        ];
    }

    /**
     * @return array{pin_hash: string, credential_id: ?int}|null
     */
    public function resolve(?string $token): ?array
    {
        if (! $token) {
            return null;
        }

        $context = Cache::get($this->key($token));

        return is_array($context) ? $context : null;
    }

    /**
     * @param  array{pin_hash: string, credential_id: ?int}  $context
     */
    public function matchesPin(array $context, string $pin): bool
    {
        return hash_equals(
            $context['pin_hash'],
            hash('sha256', strtoupper(trim($pin))),
        );
    }

    public function bindCredential(?string $token, int $credentialId): void
    {
        if (! $token) {
            return;
        }

        $key = $this->key($token);
        $context = Cache::get($key);

        if (! is_array($context)) {
            return;
        }

        Cache::put($key, [
            'pin_hash' => $context['pin_hash'],
            'credential_id' => $credentialId,
        ], now()->addMinutes(config('rate_limiting.credential_access_token.ttl_minutes')));
    }

    private function key(string $token): string
    {
        return 'credential-access-token:'.hash('sha256', $token);
    }
}
