<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->is('api', 'api/*') || $response->getStatusCode() < 400) {
            return;
        }

        $context = [
            'event' => 'api.request_failed',
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ];

        if ($response->getStatusCode() >= 500) {
            Log::error('API request failed', $context);

            return;
        }

        Log::warning('API request failed', $context);
    }
}
