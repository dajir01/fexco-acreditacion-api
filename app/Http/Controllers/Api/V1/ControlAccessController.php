<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SelectAccessEventRequest;
use App\Http\Requests\Api\V1\StoreCredentialAccessRequest;
use App\Models\ControlAccessOperator;
use App\Services\ControlAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ControlAccessController extends Controller
{
    public function events(Request $request, ControlAccessService $access): JsonResponse
    {
        /** @var ControlAccessOperator $operator */
        $operator = $request->user();
        $session = $request->attributes->get('control_access_session');

        return response()->json([
            'success' => true,
            'data' => $access->eventsFor($operator, $session),
        ]);
    }

    public function selectEvent(
        SelectAccessEventRequest $request,
        ControlAccessService $access,
    ): JsonResponse {
        $session = $request->attributes->get('control_access_session');
        $selection = $access->selectEvent(
            $session,
            $request->integer('id_feria'),
            $request->integer('numero_puerta'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Evento y puerta seleccionados correctamente.',
            'data' => $selection,
        ]);
    }

    public function storeScan(
        StoreCredentialAccessRequest $request,
        ControlAccessService $access,
    ): JsonResponse {
        /** @var ControlAccessOperator $operator */
        $operator = $request->user();
        $session = $request->attributes->get('control_access_session');
        $result = $access->registerCredentialAccess(
            $operator,
            $session,
            $request->string('token')->toString(),
            $request->string('idempotency_key')->toString(),
        );

        return response()->json([
            'success' => true,
            'duplicate' => $result['duplicate'],
            'data' => $result['data'],
        ], $result['duplicate'] ? 200 : 201);
    }

    public function scans(Request $request, ControlAccessService $access): JsonResponse
    {
        /** @var ControlAccessOperator $operator */
        $operator = $request->user();
        $session = $request->attributes->get('control_access_session');

        return response()->json([
            'success' => true,
            'data' => $access->recentScansFor($operator, $session),
        ]);
    }

    public function credentialPhoto(
        Request $request,
        ControlAccessService $access,
        int $credential,
    ): BinaryFileResponse {
        /** @var ControlAccessOperator $operator */
        $operator = $request->user();
        $session = $request->attributes->get('control_access_session');
        $path = $access->credentialPhotoPathFor($operator, $session, $credential);

        abort_unless($path !== null, 404);

        return response()->file($path);
    }
}
