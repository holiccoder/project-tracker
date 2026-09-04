<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\CurrentAdmin;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', \Illuminate\Validation\Rule::enum(ProjectStatus::class)],
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
            'data' => $projects->map(fn (Project $project): array => $this->toArray($project))->values()->all(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function show(Project|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        return response()->json($this->toArray($project->load('members')));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->normalize($request->all());
        $payload['status'] ??= ProjectStatus::Active->value;
        $payload['paid_amount'] ??= 0;
        $payload['slug'] = filled($payload['slug'] ?? null)
            ? $payload['slug']
            : InputSchemaRegistry::generatedProjectSlug((string) ($payload['name'] ?? 'project'));

        $validated = validator($payload, InputSchemaRegistry::rules('projects', 'create'))->validate();
        $adminId = CurrentAdmin::requiredId($request, 'created_by');

        $memberIds = $validated['members'] ?? null;
        unset($validated['members']);

        $project = Project::create([
            ...$validated,
            'created_by' => $adminId,
        ]);

        if ($memberIds !== null) {
            $project->members()->sync($memberIds);
        }

        return response()->json($this->toArray($project->load('members')), 201);
    }

    public function update(Request $request, Project|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $payload = $this->normalize($request->all());
        if (array_key_exists('slug', $payload) && blank($payload['slug'])) {
            unset($payload['slug']);
        }

        $validated = validator($payload, InputSchemaRegistry::rules('projects', 'update', $project->id))->validate();

        if (! $request->user('sanctum') && array_key_exists('created_by', $payload)) {
            $validated['created_by'] = validator($payload, [
                'created_by' => ['sometimes', 'integer', 'exists:admins,id'],
            ])->validate()['created_by'];
        }

        $memberIds = array_key_exists('members', $validated) ? $validated['members'] : null;
        unset($validated['members']);

        $project->update($validated);

        if ($memberIds !== null) {
            $project->members()->sync($memberIds);
        }

        return response()->json($this->toArray($project->refresh()->load('members')));
    }

    public function destroy(Project|string $project): JsonResponse
    {
        $project = $this->findProject($project);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    private function normalize(array $payload): array
    {
        foreach (['description', 'deadline', 'repo_url', 'remark'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        if (array_key_exists('members', $payload) && $payload['members'] === '') {
            $payload['members'] = [];
        }

        return $payload;
    }

    private function findProject(Project|string $identifier): ?Project
    {
        if ($identifier instanceof Project) {
            return $identifier;
        }

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
                ? $project->members->map(fn ($member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->pivot?->role,
                    'can_view_price' => (bool) ($member->pivot?->can_view_price ?? false),
                ])->values()->all()
                : null,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
        ];
    }
}
