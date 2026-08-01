<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Issue;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class IssuePolicy
{
    use HandlesAuthorization;

    /**
     * Admins (developers) may do anything; only customer rules need checking.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user instanceof Admin ? true : null;
    }

    /**
     * Customers may only read issues of projects they belong to.
     */
    public function view(User $user, Issue $issue): bool
    {
        return $issue->project->hasMember($user);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Customers cannot create or modify issues.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Issue $issue): bool
    {
        return false;
    }

    public function delete(User $user, Issue $issue): bool
    {
        return false;
    }
}
