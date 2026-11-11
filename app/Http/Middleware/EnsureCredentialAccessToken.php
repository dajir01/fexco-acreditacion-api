<?php

namespace App\Http\Middleware;

use App\Services\CredentialAccessTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCredentialAccessToken
{
    public function __construct(private readonly CredentialAccessTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tokens = $this->tokens;
        $token = $request->bearerToken();
        $context = $tokens->resolve($token);

        if (! $context) {
            return response()->json(['message' => 'El token de acreditación es inválido o expiró.'], 401);
        }

        if ($request->has('pin') && ! $tokens->matchesPin($context, $request->string('pin')->toString())) {
            return response()->json(['message' => 'El token no corresponde al PIN enviado.'], 403);
        }

        if ($request->routeIs('api.v1.credentials.download')) {
            $credential = $request->route('credential');
            $credentialId = is_object($credential)
                ? (int) ($credential->id_credencial ?? 0)
                : (int) $credential;

            if (! $context['credential_id'] || (int) $context['credential_id'] !== $credentialId) {
                return response()->json(['message' => 'El token no corresponde a esta credencial.'], 403);
            }
        }

        $request->attributes->set('credential_access_token', $context);

        return $next($request);
    }
}
