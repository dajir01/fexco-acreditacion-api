<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('control-access-login', function (Request $request): array {
            $username = mb_strtolower(trim((string) $request->input('usuario', '')));
            $ip = $request->ip() ?: 'unknown';
            $response = static fn (Request $request, array $headers) => response()->json([
                'message' => 'Demasiados intentos de inicio de sesión. Intenta nuevamente más tarde.',
            ], 429, $headers);

            return [
                Limit::perMinute(5)
                    ->by('control-access-login:account:'.hash('sha256', $username.'|'.$ip))
                    ->response($response),
                Limit::perMinute(30)
                    ->by('control-access-login:ip:'.hash('sha256', $ip))
                    ->response($response),
            ];
        });

        RateLimiter::for('control-access-scans', function (Request $request): Limit {
            $ip = $request->ip() ?: 'unknown';
            $token = hash('sha256', $request->bearerToken() ?? 'anonymous');

            return Limit::perMinute(120)
                ->by('control-access-scan:'.hash('sha256', $token.'|'.$ip))
                ->response(static fn (Request $request, array $headers) => response()->json([
                    'message' => 'Demasiados escaneos. Intenta nuevamente más tarde.',
                ], 429, $headers));
        });

        RateLimiter::for('credential-pin-validation', function (Request $request): array {
            $limits = config('rate_limiting.credential_pin');
            $ip = $request->ip() ?: 'unknown';
            $clientFingerprint = hash('sha256', $ip.'|'.$request->userAgent());
            $response = static fn (Request $request, array $headers) => response()->json([
                'message' => 'Demasiados intentos de validación. Espera unos minutos antes de volver a intentar.',
            ], 429, $headers);

            return [
                Limit::perMinute($limits['per_minute'])
                    ->by('credential-pin:client:'.$clientFingerprint)
                    ->response($response),
                Limit::perMinutes($limits['window_minutes'], $limits['window_attempts'])
                    ->by('credential-pin:ip:'.Str::slug($ip))
                    ->response($response),
            ];
        });

        RateLimiter::for('credential-registration', function (Request $request): array {
            $limits = config('rate_limiting.credential_registration');
            $ip = $request->ip() ?: 'unknown';
            $clientFingerprint = hash('sha256', $ip.'|'.$request->userAgent());
            $response = static fn (Request $request, array $headers) => response()->json([
                'message' => 'Demasiados intentos de registro. Espera unos minutos antes de volver a intentar.',
            ], 429, $headers);

            return [
                Limit::perMinute($limits['per_minute'])
                    ->by('credential-registration:client:'.$clientFingerprint)
                    ->response($response),
                Limit::perMinutes($limits['window_minutes'], $limits['window_attempts'])
                    ->by('credential-registration:ip:'.Str::slug($ip))
                    ->response($response),
            ];
        });

        RateLimiter::for('credential-download', function (Request $request): array {
            $limits = config('rate_limiting.credential_download');
            $ip = $request->ip() ?: 'unknown';
            $clientFingerprint = hash('sha256', $ip.'|'.$request->userAgent());
            $response = static fn (Request $request, array $headers) => response()->json([
                'message' => 'Demasiados intentos de descarga. Espera unos minutos antes de volver a intentar.',
            ], 429, $headers);

            return [
                Limit::perMinute($limits['per_minute'])
                    ->by('credential-download:client:'.$clientFingerprint)
                    ->response($response),
                Limit::perMinutes($limits['window_minutes'], $limits['window_attempts'])
                    ->by('credential-download:ip:'.Str::slug($ip))
                    ->response($response),
            ];
        });
    }
}
