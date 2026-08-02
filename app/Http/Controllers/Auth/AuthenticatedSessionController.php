<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $pendingToken = $request->session()->pull('pending_invitation_token');

        $request->authenticate();

        $request->session()->regenerate();

        $user = auth('web')->user();
        $redirectUrl = route('dashboard', absolute: false);

        if ($pendingToken) {
            $invitation = \App\Models\ProjectInvitation::where('token', $pendingToken)->first();
            if ($invitation && !$invitation->isExpired()) {
                if ($user->email === $invitation->email) {
                    $project = $invitation->project;
                    if (!$project->hasMember($user)) {
                        $project->members()->attach($user, ['role' => 'member']);
                    }
                    $invitation->delete();
                    $redirectUrl = route('projects.show', $project->slug);
                }
            }
        }

        return redirect()->intended($redirectUrl);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
