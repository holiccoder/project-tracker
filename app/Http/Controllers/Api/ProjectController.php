<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /**
     * List projects.
     *
     * Query parameters:
     * - status (optional)
     * - search (optional, searches name/slug)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Project::query()->withCount([
            'tasks as tasks_total',
            'tasks as tasks_done' => fn ($q) => $q->where('status', 'done'),
        ]);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('slug', 'like', $search));
        }

        $projects = $query->latest('created_at')->paginate(50);

        return response()->json([
            'data' => $projects->map(fn (Project $project) => $this->toArray($project)),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    /**
     * Show a single project.
     */
    public function show(Request $request, string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        return response()->json($this->toArray($project->load('members')));
    }

    /**
     * Create a new project.
     *
     * Body parameters:
     * - name (required)
     * - slug (required, unique)
     * - description (optional)
     * - status (optional, active|delivered|paused)
     * - amount (optional, numeric)
     * - paid_amount (optional, numeric)
     * - deadline (optional, Y-m-d)
     * - repo_url (optional)
     * - remark (optional)
     * - created_by (required, admin id)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'repo_url' => ['nullable', 'string', 'max:2048'],
            'remark' => ['nullable', 'string'],
            'created_by' => ['required', 'integer', 'exists:admins,id'],
        ]);

        $admin = Admin::find($validated['created_by']);

        $project = new Project();
        $project->forceFill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? ProjectStatus::Active->value,
            'amount' => $validated['amount'] ?? null,
            'paid_amount' => $validated['paid_amount'] ?? 0,
            'deadline' => $validated['deadline'] ?? null,
            'repo_url' => $validated['repo_url'] ?? null,
            'remark' => $validated['remark'] ?? null,
            'created_by' => $admin->id,
        ]);
        $project->save();

        return response()->json($this->toArray($project), 201);
    }

    /**
     * Update a project.
     */
    public function update(Request $request, string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects')->ignore($project->id)],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'repo_url' => ['nullable', 'string', 'max:2048'],
            'remark' => ['nullable', 'string'],
            'created_by' => ['sometimes', 'required', 'integer', 'exists:admins,id'],
        ]);

        $project->forceFill([
            'name' => $validated['name'] ?? $project->name,
            'slug' => $validated['slug'] ?? $project->slug,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $project->description,
            'status' => $validated['status'] ?? $project->status->value,
            'amount' => array_key_exists('amount', $validated) ? $validated['amount'] : $project->amount,
            'paid_amount' => array_key_exists('paid_amount', $validated) ? $validated['paid_amount'] : $project->paid_amount,
            'deadline' => array_key_exists('deadline', $validated) ? $validated['deadline'] : $project->deadline,
            'repo_url' => array_key_exists('repo_url', $validated) ? $validated['repo_url'] : $project->repo_url,
            'remark' => array_key_exists('remark', $validated) ? $validated['remark'] : $project->remark,
            'created_by' => $validated['created_by'] ?? $project->created_by,
        ]);
        $project->save();

        return response()->json($this->toArray($project));
    }

    /**
     * Delete a project.
     */
    public function destroy(string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    private function findProject(string $identifier): ?Project
    {
        if (is_numeric($identifier)) {
            return Project::find($identifier);
        }

        return Project::where('slug', $identifier)->first();
    }

    private function toArray(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'amount' => $project->amount,
            'paid_amount' => $project->paid_amount,
            'unpaid_amount' => $project->unpaid_amount,
            'deadline' => $project->deadline?->toDateString(),
            'repo_url' => $project->repo_url,
            'remark' => $project->remark,
            'created_by' => $project->created_by,
            'tasks_total' => (int) ($project->tasks_total ?? 0),
            'tasks_done' => (int) ($project->tasks_done ?? 0),
            'members' => $project->relationLoaded('members')
                ? $project->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()->all()
                : null,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
        ];
    }
}
