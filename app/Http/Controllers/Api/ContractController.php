<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Project;
use App\Support\CurrentAdmin;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_slug' => ['nullable', 'string', 'exists:projects,slug'],
        ]);

        $query = Contract::query()->with('project');

        if (! empty($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (! empty($validated['project_slug'])) {
            $project = Project::where('slug', $validated['project_slug'])->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }

        $contracts = $query->latest('created_at')->paginate(50);

        return response()->json([
            'data' => $contracts->map(fn (Contract $contract): array => $this->toArray($contract))->values()->all(),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
                'per_page' => $contracts->perPage(),
                'total' => $contracts->total(),
            ],
        ]);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json($this->toArray($contract->load('project')));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        $file = $request->file('file') ?? $request->file('file_path');
        if ($file !== null) {
            $payload['file'] = $file;
        }

        $project = $this->resolveProject($payload);
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $payload['project_id'] = $project->id;
        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('contracts', 'create'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
        ])->validate();

        $adminId = CurrentAdmin::requiredId($request, 'uploaded_by');
        $filePath = $file->store('contracts', 'local');
        unset($validated['project_slug'], $validated['file_path'], $validated['file']);

        $contract = $project->contracts()->create([
            ...$validated,
            'file_path' => $filePath,
            'uploaded_by' => $adminId,
        ]);

        return response()->json($this->toArray($contract->load('project')), 201);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $payload = $request->all();
        $file = $request->file('file') ?? $request->file('file_path');
        if ($file !== null) {
            $payload['file'] = $file;
        }

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('contracts', 'update'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
            'uploaded_by' => ['sometimes', 'nullable', 'integer', 'exists:admins,id'],
        ])->validate();

        if (array_key_exists('project_slug', $validated) && ! array_key_exists('project_id', $validated)) {
            $project = Project::where('slug', $validated['project_slug'])->first();
            if (! $project) {
                return response()->json(['message' => 'Project not found.'], 404);
            }
            $validated['project_id'] = $project->id;
        }

        $filePath = $contract->file_path;
        if ($file !== null) {
            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            $filePath = $file->store('contracts', 'local');
        }

        unset($validated['project_slug'], $validated['file_path'], $validated['file']);
        $validated['file_path'] = $filePath;
        $contract->update($validated);

        return response()->json($this->toArray($contract->refresh()->load('project')));
    }

    public function destroy(Contract $contract): JsonResponse
    {
        if (Storage::disk('local')->exists($contract->file_path)) {
            Storage::disk('local')->delete($contract->file_path);
        }

        $contract->delete();

        return response()->json(['message' => 'Contract deleted.']);
    }

    public function download(Contract $contract): Response
    {
        if (! Storage::disk('local')->exists($contract->file_path)) {
            abort(404, 'Contract file not found.');
        }

        return Storage::disk('local')->download($contract->file_path, $contract->name);
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

    private function toArray(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'project_id' => $contract->project_id,
            'project_name' => $contract->project?->name,
            'name' => $contract->name,
            'file' => [
                'path' => $contract->file_path,
                'name' => basename($contract->file_path),
                'url' => route('api.contracts.download', $contract),
            ],
            'file_url' => route('api.contracts.download', $contract),
            'uploaded_by' => $contract->uploader ? [
                'id' => $contract->uploader->id,
                'name' => $contract->uploader->name,
            ] : null,
            'created_at' => $contract->created_at?->toISOString(),
            'updated_at' => $contract->updated_at?->toISOString(),
        ];
    }
}
