<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProjectController extends Controller
{
    public function index(User $user): JsonResponse
    {
        $projects = $user->projects()->orderBy('name')->get();

        return response()->json([
            'data' => $projects->map(fn (Project $project): array => $this->toArray($project))->values()->all(),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $validated = validator($request->all(), InputSchemaRegistry::rules('user_projects', 'create'))->validate();
        $user->projects()->syncWithoutDetaching([
            $validated['project_id'] => [
                'role' => $validated['role'] ?? 'member',
                'can_view_price' => (bool) ($validated['can_view_price'] ?? false),
            ],
        ]);

        $project = $user->projects()->whereKey($validated['project_id'])->firstOrFail();

        return response()->json($this->toArray($project), 201);
    }

    public function update(Request $request, User $user, Project $project): JsonResponse
    {
        abort_unless($user->projects()->whereKey($project->id)->exists(), 404);

        $validated = validator($request->all(), InputSchemaRegistry::rules('user_projects', 'attach_update'))->validate();
        $pivot = array_intersect_key($validated, array_flip(['role', 'can_view_price']));
        if ($pivot !== []) {
            $user->projects()->updateExistingPivot($project->id, $pivot);
        }

        return response()->json($this->toArray($user->projects()->whereKey($project->id)->firstOrFail()));
    }

    public function destroy(User $user, Project $project): JsonResponse
    {
        $user->projects()->detach($project->id);

        return response()->json(['message' => 'User project membership removed.']);
    }

    private function toArray(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'role' => $project->pivot?->role,
            'can_view_price' => (bool) ($project->pivot?->can_view_price ?? false),
        ];
    }
}
