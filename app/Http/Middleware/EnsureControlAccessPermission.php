<?php

namespace App\Http\Middleware;

use App\Models\ControlAccessOperator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureControlAccessPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $operator = $request->user();

        if (! $operator instanceof ControlAccessOperator || $permissions === []) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        foreach ($permissions as $permission) {
            $hasPermission = DB::table('usuarios_control_permisos')
                ->where('id_usuario_control', $operator->getKey())
                ->where('permiso', $permission)
                ->exists();

            if (! $hasPermission) {
                return response()->json(['message' => 'No autorizado para esta acción.'], 403);
            }
        }

        return $next($request);
    }
}
