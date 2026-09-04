<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Project;
use App\Support\CurrentAdmin;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_slug' => ['nullable', 'string', 'exists:projects,slug'],
            'status' => ['nullable', Rule::enum(IssueStatus::class)],
            'severity' => ['nullable', Rule::enum(IssueSeverity::class)],
        ]);

        $query = Issue::query()->with('project');

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

        if (! empty($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }

        $issues = $query->latest('created_at')->paginate(50);

        return response()->json([
            'data' => $issues->map(fn (Issue $issue): array => $this->toArray($issue))->values()->all(),
            'meta' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
            ],
        ]);
    }

    public function show(Issue $issue): JsonResponse
    {
        return response()->json($this->toArray($issue->load(['project', 'creator', 'comments.author'])));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment');
        }

        $project = $this->resolveProject($payload);
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $payload['project_id'] = $project->id;
        $payload['severity'] ??= IssueSeverity::Normal->value;
        $payload['status'] ??= IssueStatus::Open->value;

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('issues', 'create'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
            'status' => ['sometimes', Rule::enum(IssueStatus::class)],
        ])->validate();

        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('issue-attachments', 'local')
            : null;

        $adminId = CurrentAdmin::requiredId($request, 'created_by');
        unset($validated['attachment_path'], $validated['project_slug'], $validated['attachment']);

        $issue = $project->issues()->create([
            ...$validated,
            'attachment_path' => $attachmentPath,
            'created_by' => $adminId,
        ]);

        return response()->json($this->toArray($issue->load('project')), 201);
    }

    public function update(Request $request, Issue $issue): JsonResponse
    {
        $payload = $request->all();
        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment');
        }

        foreach (['description'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('issues', 'update'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
            'created_by' => ['sometimes', 'nullable', 'integer', 'exists:admins,id'],
            'remove_attachment' => ['sometimes', 'boolean'],
        ])->validate();

        $attachmentPath = $issue->attachment_path;
        if (($validated['remove_attachment'] ?? false) && $attachmentPath) {
            if (Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $attachmentPath = null;
        }

        if ($request->hasFile('attachment')) {
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $attachmentPath = $request->file('attachment')->store('issue-attachments', 'local');
        }

        if (array_key_exists('project_slug', $validated) && ! array_key_exists('project_id', $validated)) {
            $project = Project::where('slug', $validated['project_slug'])->first();
            if (! $project) {
                return response()->json(['message' => 'Project not found.'], 404);
            }
            $validated['project_id'] = $project->id;
        }

        unset($validated['attachment_path'], $validated['attachment'], $validated['project_slug'], $validated['remove_attachment']);
        $validated['attachment_path'] = $attachmentPath;
        unset($validated['status']);

        $issue->update($validated);

        return response()->json($this->toArray($issue->refresh()->load('project')));
    }

    public function updateStatus(Request $request, Issue $issue): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required_without:action', Rule::enum(IssueStatus::class)],
            'action' => ['required_without:status', Rule::in(array_column(InputSchemaRegistry::contract()['actions']['issues'], 'name'))],
        ]);

        $target = isset($validated['action'])
            ? match ($validated['action']) {
                'start' => IssueStatus::InProgress,
                'resolve' => IssueStatus::Resolved,
                'close' => IssueStatus::Closed,
            }
            : IssueStatus::from($validated['status']);

        try {
            $issue->transitionTo($target);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->toArray($issue->refresh()->load('project')));
    }

    public function destroy(Issue $issue): JsonResponse
    {
        $issue->delete();

        return response()->json(['message' => 'Issue deleted.']);
    }

    public function downloadAttachment(Issue $issue)
    {
        abort_unless($issue->attachment_path !== null, 404);

        if (! Storage::disk('local')->exists($issue->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('local')->download($issue->attachment_path, basename($issue->attachment_path));
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

    private function toArray(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'project_id' => $issue->project_id,
            'project_name' => $issue->project?->name,
            'title' => $issue->title,
            'description' => $issue->description,
            'severity' => $issue->severity->value,
            'severity_label' => $issue->severity->label(),
            'status' => $issue->status->value,
            'status_label' => $issue->status->label(),
            'resolved_at' => $issue->resolved_at?->toISOString(),
            'attachment' => $issue->attachment_path ? [
                'path' => $issue->attachment_path,
                'name' => basename($issue->attachment_path),
                'url' => Storage::disk('local')->url($issue->attachment_path),
            ] : null,
            'attachment_url' => $issue->attachment_path ? Storage::disk('local')->url($issue->attachment_path) : null,
            'created_by' => $issue->creator ? [
                'id' => $issue->creator->id,
                'name' => $issue->creator->name,
            ] : null,
            'comments' => $issue->relationLoaded('comments')
                ? $issue->comments->map(fn ($comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->author ? [
                        'id' => $comment->author->id,
                        'name' => $comment->author->name,
                        'is_admin' => $comment->author_type === \App\Models\Admin::class,
                    ] : null,
                    'created_at' => $comment->created_at?->toISOString(),
                ])->values()->all()
                : null,
            'created_at' => $issue->created_at?->toISOString(),
            'updated_at' => $issue->updated_at?->toISOString(),
        ];
    }
}
