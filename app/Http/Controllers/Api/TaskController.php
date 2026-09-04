<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Project;
use App\Models\Task;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TaskController extends Controller
{
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
            'data' => $tasks->map(fn (Task $task): array => $this->toArray($task))->values()->all(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function show(Task $task): JsonResponse
    {
        return response()->json($this->toArray($task->load(['project', 'creator', 'comments.author'])));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->payloadWithFiles($request);
        $project = $this->resolveProject($payload);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $payload['project_id'] = $project->id;
        $payload['priority'] ??= TaskPriority::Medium->value;
        $payload['status'] ??= TaskStatus::Pending->value;

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('tasks', 'create'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ])->validate();

        if (count($validated['attachments'] ?? []) > 10) {
            throw ValidationException::withMessages([
                'attachments' => ['A task may have no more than 10 attachments.'],
            ]);
        }

        $attachmentPaths = $this->storeAttachments($validated['attachments'] ?? []);
        unset($validated['project_slug'], $validated['attachments']);

        $task = $project->tasks()->create([
            ...$validated,
            'attachments' => $attachmentPaths ?: null,
        ]);

        $this->notifyCreation($task, $project);

        return response()->json($this->toArray($task->load('project')), 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $payload = $this->payloadWithFiles($request);

        if (array_key_exists('project_slug', $payload) && ! array_key_exists('project_id', $payload)) {
            $project = Project::where('slug', $payload['project_slug'])->first();
            if (! $project) {
                return response()->json(['message' => 'Project not found.'], 404);
            }
            $payload['project_id'] = $project->id;
        }

        foreach (['description', 'due_date'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        $validated = validator($payload, [
            ...InputSchemaRegistry::rules('tasks', 'update'),
            'project_slug' => ['sometimes', 'string', 'exists:projects,slug'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'created_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'remove_attachments' => ['sometimes', 'array'],
            'remove_attachments.*' => ['string'],
        ])->validate();

        $remove = $validated['remove_attachments'] ?? [];
        $existing = array_values(array_filter($task->attachments ?? [], 'is_string'));
        $remaining = [];
        foreach ($existing as $path) {
            if (in_array($path, $remove, true)) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
                continue;
            }
            $remaining[] = $path;
        }

        $newPaths = $this->storeAttachments($validated['attachments'] ?? []);
        if (count($remaining) + count($newPaths) > 10) {
            foreach ($newPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw ValidationException::withMessages([
                'attachments' => ['A task may have no more than 10 attachments.'],
            ]);
        }

        unset($validated['attachments'], $validated['remove_attachments'], $validated['project_slug']);
        $changes = $validated;
        if ($newPaths !== [] || $remove !== []) {
            $changes['attachments'] = ($remaining + $newPaths) ?: null;
        }

        // Status is intentionally absent from the shared edit schema. State
        // changes go through updateStatus(), so a direct status payload cannot
        // mutate the state machine.
        unset($changes['status']);
        $task->update($changes);

        return response()->json($this->toArray($task->refresh()->load('project')));
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(array_column(InputSchemaRegistry::contract()['actions']['tasks'], 'name'))],
            'reject_reason' => ['required_if:action,reject', 'nullable', 'string'],
        ]);

        if ($validated['action'] === 'reject' && blank($validated['reject_reason'] ?? null)) {
            return response()->json(['message' => 'Rejection reason is required.'], 422);
        }

        $status = match ($validated['action']) {
            'confirm' => TaskStatus::Confirmed,
            'reject' => TaskStatus::Rejected,
            'start', 'restart' => TaskStatus::InProgress,
            'complete' => TaskStatus::Done,
        };

        try {
            $task->transitionTo($status, $validated['reject_reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->toArray($task->refresh()->load('project')));
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function downloadAttachment(Task $task, string $path)
    {
        abort_unless(in_array($path, $task->attachments ?? [], true), 404);

        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('public')->download($path, basename($path));
    }

    private function payloadWithFiles(Request $request): array
    {
        $payload = $request->all();
        if ($request->hasFile('attachments')) {
            $payload['attachments'] = $request->file('attachments');
        }

        return $payload;
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

    /** @param array<int, \Illuminate\Http\UploadedFile> $files */
    private function storeAttachments(array $files): array
    {
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->store('task-attachments', 'public');
        }

        return $paths;
    }

    private function notifyCreation(Task $task, Project $project): void
    {
        Notification::send(Admin::all(), new \App\Notifications\TaskDelegatedNotification($task));

        $membersToNotify = $project->members;
        if ($membersToNotify->isNotEmpty()) {
            Notification::send($membersToNotify, new \App\Notifications\TaskDelegatedNotification($task));
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
                ? $task->comments->map(fn ($comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->author ? [
                        'id' => $comment->author->id,
                        'name' => $comment->author->name,
                        'is_admin' => $comment->author_type === Admin::class,
                    ] : null,
                    'created_at' => $comment->created_at?->toISOString(),
                ])->values()->all()
                : null,
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    private function attachments(Task $task): array
    {
        return collect($task->attachments ?? [])
            ->filter(fn ($path): bool => is_string($path))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'url' => Storage::disk('public')->url($path),
            ])
            ->values()
            ->all();
    }
}
