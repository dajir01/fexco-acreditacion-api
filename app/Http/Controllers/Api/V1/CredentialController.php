<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CredentialResource;
use App\Models\Credencial;
use App\Services\CredentialImageService;
use App\Services\PinValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class CredentialController extends Controller
{
    public function show(Credencial $credential): JsonResource
    {
        return CredentialResource::make($credential->load('expositor'));
    }

    public function download(Request $request, Credencial $credential, CredentialImageService $imageService, PinValidationService $pinValidation): Response|JsonResponse
    {
        $pin = $pinValidation->findByValue($request->header('X-Credential-Pin', ''));
        abort_unless($pin && (int) $pin->id_credencial === (int) $credential->id_credencial, 404);
        abort_if((int) $credential->estado_credencial !== 1, 422, 'La credencial no está activa.');

        if ((int) $credential->modalidad_credencial === 0) {
            return response()->json([
                'downloadable' => false,
                'modality' => 'physical',
                'message' => 'Tu credencial física puede pasar a recogerse por las oficinas o el punto de acreditación.',
            ]);
        }

        $filename = 'credencial-'.$credential->id_credencial.'.png';

        return response($imageService->generateDigital($credential), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
