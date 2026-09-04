<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevLog;
use App\Models\DevLogUpdate;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevLogUpdateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dev_log_id' => ['nullable', 'integer', 'exists:dev_logs,id'],
        ]);

        $query = DevLogUpdate::query()->with('devLog.project')->latest();
        if (! empty($validated['dev_log_id'])) {
            $query->where('dev_log_id', $validated['dev_log_id']);
        }

        $updates = $query->paginate(50);

        return response()->json([
            'data' => $updates->map(fn (DevLogUpdate $update): array => $this->toArray($update))->values()->all(),
            'meta' => [
                'current_page' => $updates->currentPage(),
                'last_page' => $updates->lastPage(),
                'per_page' => $updates->perPage(),
                'total' => $updates->total(),
            ],
        ]);
    }

    public function show(DevLogUpdate $devLogUpdate): JsonResponse
    {
        return response()->json($this->toArray($devLogUpdate->load('devLog.project')));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = validator($request->all(), InputSchemaRegistry::rules('dev_log_updates', 'create'))->validate();
        $update = DevLogUpdate::create($validated);

        return response()->json($this->toArray($update->load('devLog.project')), 201);
    }

    public function storeForLog(Request $request, DevLog $devLog): JsonResponse
    {
        $validated = validator([
            'dev_log_id' => $devLog->id,
            ...$request->all(),
        ], InputSchemaRegistry::rules('dev_log_updates', 'create'))->validate();

        $update = $devLog->updates()->create(['update' => $validated['update']]);

        return response()->json($this->toArray($update->load('devLog.project')), 201);
    }

    public function update(Request $request, DevLog $devLog, DevLogUpdate $update): JsonResponse
    {
        abort_unless($update->dev_log_id === $devLog->id, 404);

        $validated = validator($request->all(), [
            'update' => InputSchemaRegistry::rules('dev_log_updates', 'update')['update'],
        ])->validate();

        $update->update($validated);

        return response()->json($this->toArray($update->refresh()->load('devLog.project')));
    }

    public function updateStandalone(Request $request, DevLogUpdate $devLogUpdate): JsonResponse
    {
        $validated = validator($request->all(), [
            'update' => InputSchemaRegistry::rules('dev_log_updates', 'update')['update'],
        ])->validate();

        $devLogUpdate->update($validated);

        return response()->json($this->toArray($devLogUpdate->refresh()->load('devLog.project')));
    }

    public function destroy(DevLogUpdate $devLogUpdate): JsonResponse
    {
        $devLogUpdate->delete();

        return response()->json(['message' => 'Development log update deleted.']);
    }

    private function toArray(DevLogUpdate $update): array
    {
        return [
            'id' => $update->id,
            'dev_log_id' => $update->dev_log_id,
            'project_id' => $update->devLog?->project_id,
            'project_name' => $update->devLog?->project?->name,
            'update' => $update->update,
            'created_at' => $update->created_at?->toISOString(),
            'updated_at' => $update->updated_at?->toISOString(),
        ];
    }
}
