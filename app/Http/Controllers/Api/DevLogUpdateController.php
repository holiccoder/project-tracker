<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevLog;
use App\Models\DevLogUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevLogUpdateController extends Controller
{
    public function store(Request $request, DevLog $devLog): JsonResponse
    {
        $validated = $request->validate([
            'update' => ['required', 'string'],
        ]);

        $devLogUpdate = $devLog->updates()->create($validated);

        return response()->json($this->toArray($devLogUpdate), 201);
    }

    public function update(
        Request $request,
        DevLog $devLog,
        DevLogUpdate $update,
    ): JsonResponse {
        abort_unless($update->dev_log_id === $devLog->id, 404);

        $validated = $request->validate([
            'update' => ['required', 'string'],
        ]);

        $update->update($validated);

        return response()->json($this->toArray($update->refresh()));
    }

    private function toArray(DevLogUpdate $update): array
    {
        return [
            'id' => $update->id,
            'dev_log_id' => $update->dev_log_id,
            'update' => $update->update,
            'created_at' => $update->created_at?->toISOString(),
            'updated_at' => $update->updated_at?->toISOString(),
        ];
    }
}
