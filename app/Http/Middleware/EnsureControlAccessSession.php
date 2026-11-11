<?php

namespace App\Http\Middleware;

use App\Models\ControlAccessOperator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureControlAccessSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $operator = $request->user();
        $token = $operator instanceof ControlAccessOperator
            ? $operator->currentAccessToken()
            : null;

        if (! $operator instanceof ControlAccessOperator || ! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => 'La sesión del operador no es válida.'], 401);
        }

        $session = DB::table('usuarios_control_sesiones')
            ->where('token_id', $token->getKey())
            ->whereNull('fin_at')
            ->first();

        if (! $session) {
            return response()->json(['message' => 'La sesión del operador no es válida.'], 401);
        }

        if (! $operator->activo) {
            DB::table('usuarios_control_sesiones')
                ->where('id', $session->id)
                ->update(['fin_at' => now(), 'updated_at' => now()]);
            $token->delete();

            return response()->json(['message' => 'La cuenta del operador está desactivada.'], 403);
        }

        DB::table('usuarios_control_sesiones')
            ->where('id', $session->id)
            ->update(['ultima_actividad_at' => now(), 'updated_at' => now()]);

        $request->attributes->set('control_access_session', $session);

        return $next($request);
    }
}
