<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ValidatePinRequest;
use App\Http\Resources\Api\V1\PinResource;
use App\Services\CredentialAccessTokenService;
use App\Services\PinValidationService;
use Illuminate\Http\JsonResponse;

class PinController extends Controller
{
    public function validate(
        ValidatePinRequest $request,
        PinValidationService $pinValidation,
        CredentialAccessTokenService $accessTokens,
    ): PinResource|JsonResponse {
        $pin = $pinValidation->findByValue($request->string('pin')->toString());

        if (! $pin) {
            return response()->json([
                'valid' => false,
                'status' => 'not_found',
                'message' => 'El PIN no existe.',
            ], 404);
        }

        if ($pin->estado === 'usado') {
            return response()->json([
                'valid' => false,
                'status' => 'used',
                'data' => ['status' => 'used'],
                'message' => 'El PIN ya fue utilizado.',
            ]);
        }

        if (! $pinValidation->isUsable($pin)) {
            return response()->json([
                'valid' => false,
                'status' => 'unavailable',
                'message' => 'El PIN no esta disponible.',
            ], 409);
        }

        return PinResource::make($pin)->additional([
            'valid' => true,
            'message' => 'PIN disponible.',
            'access_token' => $accessTokens->issue($pin)['token'],
            'expires_in' => config('rate_limiting.credential_access_token.ttl_minutes') * 60,
        ]);
    }
}
