<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Admin tokens issued via /api/auth/login (Sanctum) are accepted first.
        if ($request->bearerToken() && $request->user('sanctum') !== null) {
            return $next($request);
        }

        $provided = $request->bearerToken() ?? $request->input('token');
        $provided = (string) $provided;

        $accepted = array_filter([
            config('services.api_token'),
            config('services.dev_log_api_token'),
        ]);

        if ($accepted === []) {
            return response()->json(['message' => 'API token not configured.'], 500);
        }

        foreach ($accepted as $token) {
            if (hash_equals($token, $provided)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized.'], 401);
    }
}
