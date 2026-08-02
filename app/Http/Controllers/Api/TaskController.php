<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TaskController extends Controller
{
    /**
     * List tasks.
     *
     * Query parameters:
     * - project_id (optional)
     * - project_slug (optional)
     * - status (optional)
     * - priority (optional)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_slug' => ['nullable', 'string', 'exists:projects,slug'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
        ]);

        $query = Task::query()->with('project');

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

        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        $tasks = $query->latest('created_at')->paginate(50);

        return response()->json([
            'data' => $tasks->map(fn (Task $task) => $this->toArray($task)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Show a single task.
     */
    public function show(Task $task): JsonResponse
    {
        return response()->json($this->toArray($task->load(['creator', 'comments.author'])));
    }

    /**
     * Create a new task.
     *
     * Body parameters:
     * - project_id OR project_slug (required)
     * - title (required)
     * - description (optional)
     * - priority (optional, low|medium|high)
     * - status (optional)
     * - due_date (optional, Y-m-d)
     * - created_by (optional, user id)
     * - attachments (optional, array of files)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required_without:project_slug', 'integer', 'exists:projects,id'],
            'project_slug' => ['required_without:project_id', 'string', 'exists:projects,slug'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $project = $this->findProject($validated);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $attachmentPaths = [];
        if (! empty($validated['attachments'])) {
            foreach ($validated['attachments'] as $file) {
                $attachmentPaths[] = $file->store('task-attachments', 'public');
            }
        }

        $status = $validated['status'] ?? TaskStatus::Pending->value;
        $task = $project->tasks()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? TaskPriority::Medium->value,
            'status' => $status,
            'due_date' => $validated['due_date'] ?? null,
            'created_by' => $validated['created_by'] ?? null,
            'attachments' => $attachmentPaths ?: null,
        ]);

        $this->notifyCreation($task, $project);

        return response()->json($this->toArray($task), 201);
    }

    /**
     * Update a task.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $task->update([
            'title' => $validated['title'] ?? $task->title,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $task->description,
            'priority' => $validated['priority'] ?? $task->priority->value,
            'due_date' => array_key_exists('due_date', $validated) ? $validated['due_date'] : $task->due_date,
            'created_by' => array_key_exists('created_by', $validated) ? $validated['created_by'] : $task->created_by,
        ]);

        return response()->json($this->toArray($task));
    }

    /**
     * Update task status using state machine transitions.
     *
     * Body parameters:
     * - action (required): confirm|reject|start|restart|complete|accept|request_changes
     * - reject_reason (required when action=reject)
     */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in([
                'confirm',
                'reject',
                'start',
                'restart',
                'complete',
                'accept',
                'request_changes',
            ])],
            'reject_reason' => ['required_if:action,reject', 'nullable', 'string'],
        ]);

        if ($validated['action'] === 'reject' && blank($validated['reject_reason'] ?? null)) {
            return response()->json(['message' => '拒绝任务必须填写原因'], 422);
        }

        $status = match ($validated['action']) {
            'confirm' => TaskStatus::Confirmed,
            'reject' => TaskStatus::Rejected,
            'start', 'restart' => TaskStatus::InProgress,
            'complete' => TaskStatus::Done,
            'accept' => TaskStatus::Accepted,
            'request_changes' => TaskStatus::ChangesRequested,
        };

        try {
            $task->transitionTo($status, $validated['reject_reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->toArray($task));
    }

    /**
     * Delete a task.
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * Download a task attachment.
     */
    public function downloadAttachment(Request $request, Task $task, string $path)
    {
        abort_unless(in_array($path, $task->attachments ?? [], true), 404);

        if (! Storage::disk('public')->exists($path)) {
            abort(404, '附件不存在');
        }

        return Storage::disk('public')->download($path, basename($path));
    }

    private function findProject(array $validated): ?Project
    {
        if (! empty($validated['project_id'])) {
            return Project::find($validated['project_id']);
        }

        return Project::where('slug', $validated['project_slug'])->first();
    }

    private function notifyCreation(Task $task, Project $project): void
    {
        $admins = Admin::all();
        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\TaskDelegatedNotification($task));

        $membersToNotify = $project->members;
        if ($membersToNotify->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($membersToNotify, new \App\Notifications\TaskDelegatedNotification($task));
        }
    }

    private function toArray(Task $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority->value,
            'priority_label' => $task->priority->label(),
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'due_date' => $task->due_date?->toDateString(),
            'reject_reason' => $task->reject_reason,
            'completed_at' => $task->completed_at?->toISOString(),
            'accepted_at' => $task->accepted_at?->toISOString(),
            'created_by' => $task->creator ? [
                'id' => $task->creator->id,
                'name' => $task->creator->name,
            ] : null,
            'attachments' => $this->attachments($task),
            'comments' => $task->relationLoaded('comments')
                ? $task->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author' => $c->author ? [
                        'id' => $c->author->id,
                        'name' => $c->author->name,
                        'is_admin' => $c->author_type === Admin::class,
                    ] : null,
                    'created_at' => $c->created_at?->toISOString(),
                ])->values()->all()
                : null,
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    private function attachments(Task $task): array
    {
        $attachments = [];

        foreach ($task->attachments ?? [] as $path) {
            if (! is_string($path)) {
                continue;
            }

            $attachments[] = [
                'name' => basename($path),
                'url' => Storage::disk('public')->url($path),
            ];
        }

        return $attachments;
    }
}
