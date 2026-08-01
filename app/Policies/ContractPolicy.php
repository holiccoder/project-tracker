<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class ContractPolicy
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
     * Customers may download contracts of projects they belong to.
     */
    public function view(User $user, Contract $contract): bool
    {
        return $contract->project->hasMember($user);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Customers cannot upload or delete contracts.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Contract $contract): bool
    {
        return false;
    }

    public function delete(User $user, Contract $contract): bool
    {
        return false;
    }
}
