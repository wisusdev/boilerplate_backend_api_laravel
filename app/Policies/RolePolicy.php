<?php

namespace App\Policies;

use App\Models\User;

class RolePolicy
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
        return $user->hasPermissionTo('roles:index');
    }

    public function store(User $user): bool
    {
        return $user->hasPermissionTo('roles:store');
    }

    public function show(User $user): bool
    {
        return $user->hasPermissionTo('roles:show');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('roles:update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('roles:delete');
    }
}
