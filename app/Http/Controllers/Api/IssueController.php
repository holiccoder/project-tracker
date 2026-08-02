<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class IssueController extends Controller
{
    /**
     * List issues.
     *
     * Query parameters:
     * - project_id (optional)
     * - project_slug (optional)
     * - status (optional)
     * - severity (optional)
     */
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
            'data' => $issues->map(fn (Issue $issue) => $this->toArray($issue)),
            'meta' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
            ],
        ]);
    }

    /**
     * Show a single issue.
     */
    public function show(Issue $issue): JsonResponse
    {
        return response()->json($this->toArray($issue->load(['creator', 'comments.author'])));
    }

    /**
     * Create a new issue.
     *
     * Body parameters:
     * - project_id OR project_slug (required)
     * - title (required)
     * - description (optional)
     * - severity (optional, normal|serious|blocking)
     * - status (optional)
     * - attachment (optional, file)
     * - created_by (required, admin id)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required_without:project_slug', 'integer', 'exists:projects,id'],
            'project_slug' => ['required_without:project_id', 'string', 'exists:projects,slug'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['nullable', Rule::enum(IssueSeverity::class)],
            'status' => ['nullable', Rule::enum(IssueStatus::class)],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'created_by' => ['required', 'integer', 'exists:admins,id'],
        ]);

        $project = $this->findProject($validated);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('issue-attachments', 'local');
        }

        $issue = $project->issues()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'severity' => $validated['severity'] ?? IssueSeverity::Normal->value,
            'status' => $validated['status'] ?? IssueStatus::Open->value,
            'attachment_path' => $attachmentPath,
            'created_by' => $validated['created_by'],
        ]);

        return response()->json($this->toArray($issue), 201);
    }

    /**
     * Update an issue.
     */
    public function update(Request $request, Issue $issue): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['nullable', Rule::enum(IssueSeverity::class)],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'created_by' => ['sometimes', 'required', 'integer', 'exists:admins,id'],
        ]);

        $attachmentPath = $issue->attachment_path;
        if ($request->hasFile('attachment')) {
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            $attachmentPath = $request->file('attachment')->store('issue-attachments', 'local');
        }

        $issue->update([
            'title' => $validated['title'] ?? $issue->title,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $issue->description,
            'severity' => $validated['severity'] ?? $issue->severity->value,
            'attachment_path' => $attachmentPath,
            'created_by' => $validated['created_by'] ?? $issue->created_by,
        ]);

        return response()->json($this->toArray($issue));
    }

    /**
     * Update issue status using state machine transitions.
     *
     * Body parameters:
     * - status (required): in_progress|resolved|closed
     */
    public function updateStatus(Request $request, Issue $issue): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(IssueStatus::class)],
        ]);

        $target = IssueStatus::from($validated['status']);

        try {
            $issue->transitionTo($target);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->toArray($issue));
    }

    /**
     * Delete an issue.
     */
    public function destroy(Issue $issue): JsonResponse
    {
        $issue->delete();

        return response()->json(['message' => 'Issue deleted.']);
    }

    /**
     * Download an issue attachment.
     */
    public function downloadAttachment(Issue $issue)
    {
        abort_unless($issue->attachment_path !== null, 404);

        if (! Storage::disk('local')->exists($issue->attachment_path)) {
            abort(404, '附件不存在');
        }

        return Storage::disk('local')->download($issue->attachment_path, basename($issue->attachment_path));
    }

    private function findProject(array $validated): ?Project
    {
        if (! empty($validated['project_id'])) {
            return Project::find($validated['project_id']);
        }

        return Project::where('slug', $validated['project_slug'])->first();
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
            'attachment_url' => $issue->attachment_path ? Storage::disk('local')->url($issue->attachment_path) : null,
            'created_by' => $issue->creator ? [
                'id' => $issue->creator->id,
                'name' => $issue->creator->name,
            ] : null,
            'comments' => $issue->relationLoaded('comments')
                ? $issue->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author' => $c->author ? [
                        'id' => $c->author->id,
                        'name' => $c->author->name,
                        'is_admin' => $c->author_type === \App\Models\Admin::class,
                    ] : null,
                    'created_at' => $c->created_at?->toISOString(),
                ])->values()->all()
                : null,
            'created_at' => $issue->created_at?->toISOString(),
            'updated_at' => $issue->updated_at?->toISOString(),
        ];
    }
}
