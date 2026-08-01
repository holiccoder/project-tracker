<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\ClientData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function show(Request $request, Project $project, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load('creator');

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
            'due_date' => ['nullable', 'date'],
        ]);

        $project->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', '任务已委派');
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
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', '任务状态已更新');
    }
}
