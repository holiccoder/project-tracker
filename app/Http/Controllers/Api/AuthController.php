<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Log in an admin and issue an API token.
     *
     * Body parameters:
     * - email (required)
     * - password (required)
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $admin->createToken('browser-extension')->plainTextToken;

        return response()->json([
            'token' => $token,
            'admin' => $this->toArray($admin),
        ]);
    }

    /**
     * Revoke the current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * The currently authenticated admin.
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user('sanctum');

        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return response()->json($this->toArray($admin));
    }

    private function toArray(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ];
    }
}
