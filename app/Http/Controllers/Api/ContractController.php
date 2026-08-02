<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ContractController extends Controller
{
    /**
     * List contracts.
     *
     * Query parameters:
     * - project_id (optional)
     * - project_slug (optional)
     */
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
            'data' => $contracts->map(fn (Contract $contract) => $this->toArray($contract)),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
                'per_page' => $contracts->perPage(),
                'total' => $contracts->total(),
            ],
        ]);
    }

    /**
     * Show a single contract.
     */
    public function show(Contract $contract): JsonResponse
    {
        return response()->json($this->toArray($contract));
    }

    /**
     * Upload a new contract.
     *
     * Body parameters:
     * - project_id OR project_slug (required)
     * - name (required)
     * - file (required)
     * - uploaded_by (required, admin id)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required_without:project_slug', 'integer', 'exists:projects,id'],
            'project_slug' => ['required_without:project_id', 'string', 'exists:projects,slug'],
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:51200'],
            'uploaded_by' => ['required', 'integer', 'exists:admins,id'],
        ]);

        $project = $this->findProject($validated);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $filePath = $request->file('file')->store('contracts', 'local');

        $contract = $project->contracts()->create([
            'name' => $validated['name'],
            'file_path' => $filePath,
            'uploaded_by' => $validated['uploaded_by'],
        ]);

        return response()->json($this->toArray($contract), 201);
    }

    /**
     * Update a contract name.
     */
    public function update(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:51200'],
            'uploaded_by' => ['sometimes', 'required', 'integer', 'exists:admins,id'],
        ]);

        $filePath = $contract->file_path;
        if ($request->hasFile('file')) {
            if (Storage::disk('local')->exists($contract->file_path)) {
                Storage::disk('local')->delete($contract->file_path);
            }
            $filePath = $request->file('file')->store('contracts', 'local');
        }

        $contract->update([
            'name' => $validated['name'] ?? $contract->name,
            'file_path' => $filePath,
            'uploaded_by' => $validated['uploaded_by'] ?? $contract->uploaded_by,
        ]);

        return response()->json($this->toArray($contract));
    }

    /**
     * Delete a contract.
     */
    public function destroy(Contract $contract): JsonResponse
    {
        if (Storage::disk('local')->exists($contract->file_path)) {
            Storage::disk('local')->delete($contract->file_path);
        }

        $contract->delete();

        return response()->json(['message' => 'Contract deleted.']);
    }

    /**
     * Download a contract file.
     */
    public function download(Contract $contract): Response
    {
        if (! Storage::disk('local')->exists($contract->file_path)) {
            abort(404, '合同文件不存在');
        }

        return Storage::disk('local')->download($contract->file_path, $contract->name);
    }

    private function findProject(array $validated): ?Project
    {
        if (! empty($validated['project_id'])) {
            return Project::find($validated['project_id']);
        }

        return Project::where('slug', $validated['project_slug'])->first();
    }

    private function toArray(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'project_id' => $contract->project_id,
            'project_name' => $contract->project?->name,
            'name' => $contract->name,
            'file_url' => Storage::disk('local')->url($contract->file_path),
            'uploaded_by' => $contract->uploader ? [
                'id' => $contract->uploader->id,
                'name' => $contract->uploader->name,
            ] : null,
            'created_at' => $contract->created_at?->toISOString(),
            'updated_at' => $contract->updated_at?->toISOString(),
        ];
    }
}
