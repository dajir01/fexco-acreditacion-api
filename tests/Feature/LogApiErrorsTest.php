<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

test('logs API 404 responses with request and client context', function (): void {
    Log::spy();

    $this->withHeader('User-Agent', 'CredentialMobile/1.0')
        ->getJson('/api/unknown-endpoint')
        ->assertNotFound();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'API request failed'
                && $context['event'] === 'api.request_failed'
                && $context['method'] === 'GET'
                && $context['path'] === 'api/unknown-endpoint'
                && $context['route'] === null
                && $context['status'] === 404
                && $context['client_ip'] === '127.0.0.1'
                && $context['user_agent'] === 'CredentialMobile/1.0';
        });
});

test('logs API 5xx responses as errors with the matched route', function (): void {
    Log::spy();

    Route::get('/api/logging-test', fn (): JsonResponse => response()->json([
        'message' => 'Unavailable',
    ], 503))->name('api.logging-test');

    $this->getJson('/api/logging-test')->assertStatus(503);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'API request failed'
                && $context['event'] === 'api.request_failed'
                && $context['method'] === 'GET'
                && $context['path'] === 'api/logging-test'
                && $context['route'] === 'api.logging-test'
                && $context['status'] === 503;
        });
});
