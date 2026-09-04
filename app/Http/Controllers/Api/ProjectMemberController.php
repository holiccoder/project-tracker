<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $members = $project->members()->orderBy('name')->get();

        return response()->json([
            'data' => $members->map(fn (User $user): array => $this->toArray($user))->values()->all(),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = validator($request->all(), InputSchemaRegistry::rules('project_members', 'create'))->validate();
        $project->members()->syncWithoutDetaching([
            $validated['user_id'] => [
                'role' => $validated['role'] ?? 'member',
                'can_view_price' => (bool) ($validated['can_view_price'] ?? false),
            ],
        ]);

        $user = $project->members()->whereKey($validated['user_id'])->firstOrFail();

        return response()->json($this->toArray($user), 201);
    }

    public function update(Request $request, Project $project, User $user): JsonResponse
    {
        abort_unless($project->hasMember($user), 404);

        $validated = validator($request->all(), InputSchemaRegistry::rules('project_members', 'attach_update'))->validate();
        $pivot = array_intersect_key($validated, array_flip(['role', 'can_view_price']));
        if ($pivot !== []) {
            $project->members()->updateExistingPivot($user->id, $pivot);
        }

        return response()->json($this->toArray($project->members()->whereKey($user->id)->firstOrFail()));
    }

    public function destroy(Project $project, User $user): JsonResponse
    {
        $project->members()->detach($user->id);

        return response()->json(['message' => 'Project member removed.']);
    }

    private function toArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'wechat' => $user->wechat,
            'phone' => $user->phone,
            'role' => $user->pivot?->role,
            'can_view_price' => (bool) ($user->pivot?->can_view_price ?? false),
        ];
    }
}
