<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\ClientData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function show(Request $request, Project $project, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load(['creator', 'comments.author']);

        return Inertia::render('Projects/Tasks/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
            ],
            'task' => ClientData::task($task),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Task::class, $project]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $attachmentPaths = [];

        if (! empty($validated['attachments'])) {
            foreach ($validated['attachments'] as $file) {
                $attachmentPaths[] = $file->store('task-attachments', 'public');
            }
        }

        $task = $project->tasks()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'status' => TaskStatus::Pending->value,
            'created_by' => $request->user()->id,
            'attachments' => $attachmentPaths ?: null,
        ]);

        // Notify all admins
        $admins = \App\Models\Admin::all();
        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\TaskDelegatedNotification($task));

        // Notify other project members/clients
        $membersToNotify = $project->members->where('id', '!=', $request->user()->id);
        if ($membersToNotify->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($membersToNotify, new \App\Notifications\TaskDelegatedNotification($task));
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', '任务已委派');
    }

    public function downloadAttachment(Request $request, Project $project, Task $task, string $path)
    {
        $this->authorize('view', $task);

        abort_unless($task->project_id === $project->id, 404);
        abort_unless(in_array($path, $task->attachments ?? [], true), 404);

        if (! Storage::disk('public')->exists($path)) {
            abort(404, '附件不存在');
        }

        return Storage::disk('public')->download($path, basename($path));
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
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
        ]);

        $this->authorize('updateStatus', [$task, $validated['action']]);

        if ($validated['action'] === 'reject') {
            $request->validate([
                'reject_reason' => ['required', 'string'],
            ]);
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

            $user = auth('web')->user();
            if ($task->creator && (!$user || $user->id !== $task->creator->id)) {
                $task->creator->notify(new \App\Notifications\TaskStatusChangedNotification($task));
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', '任务状态已更新');
    }
}
