<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckPersonRequest;
use App\Http\Requests\Api\V1\RegisterCredentialRequest;
use App\Http\Resources\Api\V1\CredentialResource;
use App\Http\Resources\Api\V1\PersonResource;
use App\Services\CredentialAccessTokenService;
use App\Services\CredentialRegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function checkPerson(CheckPersonRequest $request, CredentialRegistrationService $registrationService): JsonResponse
    {
        $result = $registrationService->checkPerson(
            $request->string('pin')->toString(),
            $request->string('numero_documento')->toString(),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'already_accredited' => $result['already_accredited'],
                'person' => $result['person'] ? PersonResource::make($result['person']) : null,
                'credential' => $result['credential'] ? CredentialResource::make($result['credential']) : null,
            ],
        ]);
    }

    public function store(
        RegisterCredentialRequest $request,
        CredentialRegistrationService $registrationService,
        CredentialAccessTokenService $accessTokens,
    ): JsonResponse {
        $result = $registrationService->register(
            $request->validated(),
            $request->file('foto'),
        );
        $accessTokens->bindCredential(
            $request->bearerToken(),
            $result['credential']->id_credencial,
        );

        return response()->json([
            'success' => true,
            'message' => 'Registro completado correctamente.',
            'data' => [
                'person' => PersonResource::make($result['person']),
                'credential' => CredentialResource::make($result['credential']),
            ],
        ], 201);
    }
}
