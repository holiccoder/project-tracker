<?php

namespace App\Http\Controllers\Api;

use App\Enums\DevLogCategory;
use App\Enums\DevLogStatus;
use App\Http\Controllers\Controller;
use App\Models\DevLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DevLogController extends Controller
{
    /**
     * List development logs.
     *
     * Query parameters:
     * - project_id
     * - project_slug
     * - status
     * - category
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_slug' => ['nullable', 'string', 'exists:projects,slug'],
            'status' => ['nullable', Rule::enum(DevLogStatus::class)],
            'category' => ['nullable', Rule::enum(DevLogCategory::class)],
        ]);

        $query = DevLog::query()->with('project')->latest('date');

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
            'data' => $logs->map(fn (DevLog $log) => $this->toArray($log)),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Create a new development log.
     *
     * Body parameters:
     * - project_id OR project_slug (required)
     * - content (required)
     * - date (optional, Y-m-d)
     * - status (optional, in_progress|completed)
     * - category (optional, agent_independent|human_agent_collaboration)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required_without:project_slug', 'integer', 'exists:projects,id'],
            'project_slug' => ['required_without:project_id', 'string', 'exists:projects,slug'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(DevLogStatus::class)],
            'category' => ['nullable', Rule::enum(DevLogCategory::class)],
        ]);

        $project = ! empty($validated['project_id'])
            ? Project::find($validated['project_id'])
            : Project::where('slug', $validated['project_slug'])->first();

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $log = $project->devLogs()->create([
            'date' => $validated['date'] ?? now()->toDateString(),
            'content' => $validated['content'],
            'status' => $validated['status'] ?? DevLogStatus::InProgress->value,
            'category' => $validated['category'] ?? DevLogCategory::AgentIndependent->value,
        ]);

        return response()->json($this->toArray($log), 201);
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
            'created_at' => $log->created_at?->toISOString(),
            'updated_at' => $log->updated_at?->toISOString(),
        ];
    }
}
