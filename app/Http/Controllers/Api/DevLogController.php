<?php

namespace App\Http\Controllers\Api;

use App\Enums\DevLogCategory;
use App\Enums\DevLogStatus;
use App\Http\Controllers\Controller;
use App\Models\DevLog;
use App\Models\Project;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DevLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_slug' => ['nullable', 'string', 'exists:projects,slug'],
            'status' => ['nullable', Rule::enum(DevLogStatus::class)],
            'category' => ['nullable', Rule::enum(DevLogCategory::class)],
        ]);

        $query = DevLog::query()->with(['project', 'latestUpdate'])->latest('date');

        if (! empty($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (! empty($validated['project_slug'])) {
            $project = Project::where('slug', $validated['project_slug'])->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $logs = $query->paginate(50);

        return response()->json([
            'data' => $logs->map(fn (DevLog $log): array => $this->toArray($log))->values()->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function show(DevLog $devLog): JsonResponse
    {
        return response()->json($this->toArray($devLog->load(['project', 'latestUpdate', 'updates'])));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        $project = $this->resolveProject($payload);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $payload['project_id'] = $project->id;
        $payload['date'] ??= now()->toDateString();
        $payload['status'] ??= DevLogStatus::InProgress->value;
        $payload['category'] ??= DevLogCategory::AgentIndependent->value;

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('dev_logs', 'create'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
        ])->validate();

        unset($validated['project_slug']);
        $log = $project->devLogs()->create($validated);

        return response()->json($this->toArray($log->load('project')), 201);
    }

    public function update(Request $request, DevLog $devLog): JsonResponse
    {
        $payload = $request->all();
        if (array_key_exists('project_slug', $payload) && ! array_key_exists('project_id', $payload)) {
            $project = Project::where('slug', $payload['project_slug'])->first();
            if (! $project) {
                return response()->json(['message' => 'Project not found.'], 404);
            }
            $payload['project_id'] = $project->id;
        }

        if (array_key_exists('date', $payload) && $payload['date'] === '') {
            $payload['date'] = null;
        }

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('dev_logs', 'update'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
        ])->validate();

        unset($validated['project_slug']);
        $devLog->update($validated);

        return response()->json($this->toArray($devLog->refresh()->load('project', 'latestUpdate')));
    }

    public function destroy(DevLog $devLog): JsonResponse
    {
        $devLog->delete();

        return response()->json(['message' => 'Development log deleted.']);
    }

    public function batchStore(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'logs' => ['required', 'array', 'min:1'],
            'logs.*' => ['required', 'array'],
        ]);

        $logs = [];
        foreach ($validated['logs'] as $entry) {
            $payload = [
                'project_id' => $project->id,
                ...$entry,
            ];
            $payload['date'] ??= now()->toDateString();
            $payload['status'] ??= DevLogStatus::InProgress->value;
            $payload['category'] ??= DevLogCategory::AgentIndependent->value;
            $entryValidated = validator($payload, InputSchemaRegistry::rules('dev_logs', 'create'))->validate();
            $logs[] = $project->devLogs()->create($entryValidated);
        }

        return response()->json([
            'data' => collect($logs)->map(fn (DevLog $log): array => $this->toArray($log->load('project')))->values()->all(),
        ], 201);
    }

    private function resolveProject(array $payload): ?Project
    {
        if (! empty($payload['project_id'])) {
            return Project::find($payload['project_id']);
        }

        if (! empty($payload['project_slug'])) {
            return Project::where('slug', $payload['project_slug'])->first();
        }

        return null;
    }

    private function toArray(DevLog $log): array
    {
        return [
            'id' => $log->id,
            'project_id' => $log->project_id,
            'project_name' => $log->project?->name,
            'date' => $log->date->toDateString(),
            'content' => $log->content,
            'status' => $log->status->value,
            'status_label' => $log->status->label(),
            'category' => $log->category->value,
            'category_label' => $log->category->label(),
            'latest_update' => $log->latestUpdate ? [
                'id' => $log->latestUpdate->id,
                'dev_log_id' => $log->latestUpdate->dev_log_id,
                'update' => $log->latestUpdate->update,
                'created_at' => $log->latestUpdate->created_at?->toISOString(),
                'updated_at' => $log->latestUpdate->updated_at?->toISOString(),
            ] : null,
            'updates' => $log->relationLoaded('updates')
                ? $log->updates->map(fn ($update): array => [
                    'id' => $update->id,
                    'dev_log_id' => $update->dev_log_id,
                    'update' => $update->update,
                    'created_at' => $update->created_at?->toISOString(),
                    'updated_at' => $update->updated_at?->toISOString(),
                ])->values()->all()
                : null,
            'created_at' => $log->created_at?->toISOString(),
            'updated_at' => $log->updated_at?->toISOString(),
        ];
    }
}
