<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\DevLog;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Whitelisted serialization for client (Inertia) data.
 *
 * Every field returned to the customer-facing frontend must be
 * explicitly listed here — never `toArray()` a model wholesale,
 * as that would leak internal fields such as users.remark.
 */
class ClientData
{
    public static function projectSummary(Project $project, User $user): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'amount' => $project->amount,
            'paid_amount' => $project->paid_amount,
            'unpaid_amount' => $project->unpaid_amount,
            'deadline' => $project->deadline?->toDateString(),
            'tasks_total' => (int) $project->tasks_count,
            'tasks_done' => (int) $project->tasks_done,
            'role' => $project->roleOf($user)?->value,
        ];
    }

    public static function projectDetail(Project $project, User $user): array
    {
        $summary = self::projectSummary($project, $user);
        $summary['members'] = $project->members->map(
            fn (User $member) => self::member($member),
        )->values()->all();

        return $summary;
    }

    public static function member(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name];
    }

    public static function task(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority->value,
            'priority_label' => $task->priority->label(),
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'due_date' => $task->due_date?->toDateString(),
            'reject_reason' => $task->reject_reason,
            'created_by' => $task->creator
                ? self::member($task->creator)
                : null,
            'completed_at' => $task->completed_at?->toISOString(),
            'accepted_at' => $task->accepted_at?->toISOString(),
            'created_at' => $task->created_at?->toISOString(),
        ];
    }

    public static function devLog(DevLog $devLog): array
    {
        return [
            'id' => $devLog->id,
            'date' => $devLog->date->toDateString(),
            'content' => $devLog->content,
            'hours_spent' => $devLog->hours_spent,
            'task' => $devLog->task
                ? ['id' => $devLog->task->id, 'title' => $devLog->task->title]
                : null,
        ];
    }

    public static function issue(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'title' => $issue->title,
            'description' => $issue->description,
            'severity' => $issue->severity->value,
            'severity_label' => $issue->severity->label(),
            'status' => $issue->status->value,
            'status_label' => $issue->status->label(),
            'resolved_at' => $issue->resolved_at?->toISOString(),
            'created_at' => $issue->created_at?->toISOString(),
        ];
    }

    public static function contract(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'name' => $contract->name,
            'created_at' => $contract->created_at?->toISOString(),
        ];
    }
}
