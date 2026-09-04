<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Http\Request;

final class CurrentAdmin
{
    public static function id(Request $request, ?string $legacyKey = null): ?int
    {
        $admin = $request->user('sanctum');

        if ($admin instanceof Admin) {
            return $admin->id;
        }

        if ($legacyKey !== null && $request->filled($legacyKey)) {
            return $request->integer($legacyKey);
        }

        return null;
    }

    public static function requiredId(Request $request, ?string $legacyKey = null): int
    {
        $id = self::id($request, $legacyKey);

        abort_unless($id !== null && Admin::whereKey($id)->exists(), 422, 'Authenticated admin is required.');

        return $id;
    }
}
