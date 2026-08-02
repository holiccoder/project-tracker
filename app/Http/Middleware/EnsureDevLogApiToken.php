<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevLogApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.dev_log_api_token');

        if (empty($expected)) {
            return response()->json(['message' => 'API token not configured.'], 500);
        }

        $provided = $request->bearerToken() ?? $request->input('token');

        if (! hash_equals($expected, (string) $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
