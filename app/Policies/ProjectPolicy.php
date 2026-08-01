<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class ProjectPolicy
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
     * Customers may only view projects they are members of.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Customers cannot create projects; only the developer can.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Project $project): bool
    {
        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
