<?php

use App\Http\Controllers\Api\V1\ControlAccessController;
use App\Http\Controllers\Api\V1\CredentialController;
use App\Http\Controllers\Api\V1\OperatorAuthController;
use App\Http\Controllers\Api\V1\PinController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Middleware\EnsureControlAccessPermission;
use App\Http\Middleware\EnsureControlAccessSession;
use App\Http\Middleware\EnsureCredentialAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (): void {
    Route::prefix('control-access')->group(function (): void {
        Route::post('/login', [OperatorAuthController::class, 'login'])
            ->middleware('throttle:control-access-login')
            ->name('api.v1.control-access.login');

        Route::middleware(['auth:sanctum', EnsureControlAccessSession::class])->group(function (): void {
            Route::get('/me', [OperatorAuthController::class, 'me'])
                ->name('api.v1.control-access.me');
            Route::post('/logout', [OperatorAuthController::class, 'logout'])
                ->name('api.v1.control-access.logout');

            Route::get('/events', [ControlAccessController::class, 'events'])
                ->middleware(EnsureControlAccessPermission::class.':controlaccesos.validar_qr')
                ->name('api.v1.control-access.events');
            Route::put('/session/event', [ControlAccessController::class, 'selectEvent'])
                ->middleware(EnsureControlAccessPermission::class.':controlaccesos.validar_qr')
                ->name('api.v1.control-access.session.event');
            Route::get('/scans', [ControlAccessController::class, 'scans'])
                ->middleware(EnsureControlAccessPermission::class.':controlaccesos.validar_qr,controlaccesos.registrar_ingreso')
                ->name('api.v1.control-access.scans.index');
            Route::get('/credentials/{credential}/photo', [ControlAccessController::class, 'credentialPhoto'])
                ->whereNumber('credential')
                ->middleware(EnsureControlAccessPermission::class.':controlaccesos.validar_qr,controlaccesos.registrar_ingreso')
                ->name('api.v1.control-access.credentials.photo');
            Route::post('/scans', [ControlAccessController::class, 'storeScan'])
                ->middleware([
                    'throttle:control-access-scans',
                    EnsureControlAccessPermission::class.':controlaccesos.validar_qr,controlaccesos.registrar_ingreso',
                ])
                ->name('api.v1.control-access.scans.store');
        });
    });

    Route::post('/pins/validate', [PinController::class, 'validate'])
        ->middleware('throttle:credential-pin-validation')
        ->name('api.v1.pins.validate');
    Route::post('/registrations', [RegistrationController::class, 'store'])
        ->middleware(['throttle:credential-registration', EnsureCredentialAccessToken::class])
        ->name('api.v1.registrations.store');
    Route::post('/registrations/check-person', [RegistrationController::class, 'checkPerson'])
        ->middleware(['throttle:credential-pin-validation', EnsureCredentialAccessToken::class])
        ->name('api.v1.registrations.check-person');
    Route::get('/credentials/{credential}/download', [CredentialController::class, 'download'])
        ->whereNumber('credential')
        ->middleware(['throttle:credential-download', EnsureCredentialAccessToken::class])
        ->name('api.v1.credentials.download');
});
