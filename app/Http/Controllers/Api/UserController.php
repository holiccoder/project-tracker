<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = User::query()->withCount('projects');

        if (! empty($validated['search'])) {
            $search = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('wechat', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        $users = $query->latest('created_at')->paginate(50);

        return response()->json([
            'data' => $users->map(fn (User $user): array => $this->toArray($user))->values()->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->toArray($user->load('projects:id,name')));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->normalize($request);
        $validated = validator($payload, InputSchemaRegistry::rules('users', 'create'))->validate();

        $user = User::create([
            ...$validated,
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json($this->toArray($user), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $payload = $this->normalize($request);
        $validated = validator($payload, InputSchemaRegistry::rules('users', 'update', $user->id))->validate();

        if (array_key_exists('password', $validated) && blank($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($this->toArray($user->refresh()));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    private function normalize(Request $request): array
    {
        $payload = $request->all();

        foreach (['wechat', 'phone', 'remark'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        if (array_key_exists('password', $payload) && $payload['password'] === '') {
            $payload['password'] = null;
        }

        return $payload;
    }

    private function toArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'wechat' => $user->wechat,
            'phone' => $user->phone,
            'remark' => $user->remark,
            'projects_count' => (int) ($user->projects_count ?? ($user->relationLoaded('projects') ? $user->projects->count() : 0)),
            'projects' => $user->relationLoaded('projects')
                ? $user->projects->map(fn ($project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                ])->values()->all()
                : null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
