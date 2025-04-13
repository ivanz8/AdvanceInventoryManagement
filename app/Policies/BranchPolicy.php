<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the branch.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Branch  $branch
     * @return bool
     */
    public function update(User $user, Branch $branch)
    {
        // Add your authorization logic here
        // For example, only allow admin users to update branches
        return $user->role === 'admin';
    }
} 