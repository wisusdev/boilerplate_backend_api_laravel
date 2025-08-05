<?php

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function index(User $user): bool
    {
        return $user->can('permissions:index');
    }

    public function permissionsByRole(User $user): bool
    {
        return $user->can('permissions:by-role');
    }
}
