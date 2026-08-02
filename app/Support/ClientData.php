<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\DevLog;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

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
        $canViewPrice = $project->canViewPriceFor($user);

        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'repo_url' => $project->repo_url,
            'amount' => $canViewPrice ? $project->amount : null,
            'paid_amount' => $canViewPrice ? $project->paid_amount : null,
            'unpaid_amount' => $canViewPrice ? $project->unpaid_amount : null,
            'can_view_price' => $canViewPrice,
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

    public static function comment(\App\Models\Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $comment->author ? [
                'id' => $comment->author->id,
                'name' => $comment->author->name,
                'is_admin' => $comment->author_type === \App\Models\Admin::class,
            ] : [
                'id' => 0,
                'name' => '已注销用户',
                'is_admin' => false,
            ],
            'attachments' => self::commentAttachments($comment),
            'created_at' => $comment->created_at?->toISOString(),
        ];
    }

    public static function commentAttachments(\App\Models\Comment $comment): array
    {
        $attachments = [];

        foreach ($comment->attachments ?? [] as $path) {
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
            'attachments' => self::taskAttachments($task),
            'comments' => $task->comments->map(
                fn (\App\Models\Comment $comment) => self::comment($comment),
            )->values()->all(),
        ];
    }

    public static function taskAttachments(Task $task): array
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

    public static function devLog(DevLog $devLog): array
    {
        return [
            'id' => $devLog->id,
            'date' => $devLog->date->toDateString(),
            'content' => $devLog->content,
            'status' => $devLog->status->value,
            'status_label' => $devLog->status->label(),
            'category' => $devLog->category->value,
            'category_label' => $devLog->category->label(),
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
            'has_attachment' => $issue->attachment_path !== null,
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

    public static function payment(\App\Models\Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'date' => $payment->date->toDateString(),
            'remark' => $payment->remark,
        ];
    }
}
