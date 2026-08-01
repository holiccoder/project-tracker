<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\Task;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Admins (developers) may do anything; only customer rules need checking.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user instanceof Admin ? true : null;
    }

    public function view(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Any project member may delegate a task; admins via before().
     */
    public function create(User $user, $project): bool
    {
        return $project->hasMember($user);
    }

    public function update(User $user, Task $task): bool
    {
        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }

    /**
     * Status transitions are gated by the state machine actor:
     * confirm / reject / start / complete are developer actions;
     * accept / request_changes are customer (project member) actions.
     */
    public function updateStatus(User $user, Task $task, string $action): bool
    {
        $developerActions = [
            'confirm',
            'reject',
            'start',
            'complete',
            'restart',
        ];

        if (in_array($action, $developerActions, true)) {
            return $user instanceof Admin;
        }

        return match ($action) {
            'accept' => $task->status === TaskStatus::Done && $task->project->hasMember($user),
            'request_changes' => $task->status === TaskStatus::Done && $task->project->hasMember($user),
            default => false,
        };
    }
}
