<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switch the session cookie name for Filament admin requests so that
 * admin and web user sessions are fully isolated.
 *
 * This middleware must run before Laravel's StartSession middleware.
 */
class SwitchAdminSessionCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldUseAdminCookie($request)) {
            config(['session.cookie' => 'admin_session']);
        }

        return $next($request);
    }

    private function shouldUseAdminCookie(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return true;
        }

        // Livewire update requests from Filament pages still need the admin cookie.
        if ($request->is('livewire/*')) {
            $referer = $request->header('referer', '');
            $component = $request->input('components.0.snapshot');

            if (str_contains($referer, '/admin')) {
                return true;
            }

            // Fallback: check if the Livewire component snapshot mentions Filament.
            if (is_string($component) && str_contains($component, 'Filament')) {
                return true;
            }
        }

        return false;
    }
}
