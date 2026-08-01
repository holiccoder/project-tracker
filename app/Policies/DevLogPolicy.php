<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\DevLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class DevLogPolicy
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
     * Customers may only read dev logs of projects they belong to.
     */
    public function view(User $user, DevLog $devLog): bool
    {
        return $devLog->project->hasMember($user);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Customers cannot write dev logs.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DevLog $devLog): bool
    {
        return false;
    }

    public function delete(User $user, DevLog $devLog): bool
    {
        return false;
    }
}
