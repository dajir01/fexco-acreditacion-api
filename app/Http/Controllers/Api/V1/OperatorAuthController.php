<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OperatorLoginRequest;
use App\Models\ControlAccessOperator;
use App\Services\ControlAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class OperatorAuthController extends Controller
{
    public function login(OperatorLoginRequest $request, ControlAccessService $access): JsonResponse
    {
        $validated = $request->validated();
        $username = mb_strtolower(trim($validated['usuario']));
        $operator = ControlAccessOperator::query()
            ->where('usuario', $username)
            ->first();

        if (! $operator || ! $operator->activo || ! Hash::check($validated['password'], $operator->password)) {
            return response()->json([
                'message' => 'Las credenciales no coinciden con nuestros registros.',
            ], 401);
        }

        $login = DB::transaction(function () use ($operator, $request, $validated): array {
            $expiresAt = now()->addHours(12);
            $token = $operator->createToken('control-accesos-mobile', ['control-access'], $expiresAt);
            $accessToken = $token->accessToken;

            DB::table('usuarios_control_sesiones')->insert([
                'id_usuario_control' => $operator->getKey(),
                'token_id' => $accessToken->getKey(),
                'dispositivo_ref' => $validated['dispositivo_ref'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'inicio_at' => now(),
                'ultima_actividad_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toIso8601String(),
                'expires_in' => 12 * 60 * 60,
            ];
        });
        $operatorPayload = $access->operatorPayload($operator);
        $events = in_array('controlaccesos.validar_qr', $operatorPayload['permissions'], true)
            ? $access->eventsFor($operator)
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $login['token'],
                'token_type' => 'Bearer',
                'expires_at' => $login['expires_at'],
                'expires_in' => $login['expires_in'],
                'operator' => $operatorPayload,
                'events' => $events,
            ],
        ]);
    }

    public function me(Request $request, ControlAccessService $access): JsonResponse
    {
        /** @var ControlAccessOperator $operator */
        $operator = $request->user();
        $session = $request->attributes->get('control_access_session');

        return response()->json([
            'success' => true,
            'data' => [
                'operator' => $access->operatorPayload($operator),
                'selection' => $access->selectionPayload($session),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ControlAccessOperator $operator */
        $operator = $request->user();
        $token = $operator->currentAccessToken();
        $session = $request->attributes->get('control_access_session');

        if ($token instanceof PersonalAccessToken) {
            DB::table('usuarios_control_sesiones')
                ->where('id', $session->id)
                ->whereNull('fin_at')
                ->update(['fin_at' => now(), 'updated_at' => now()]);
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
