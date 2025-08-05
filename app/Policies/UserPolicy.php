<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
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
        return $user->hasPermissionTo('users:index');
    }

    public function store(User $user): bool
    {
        return $user->hasPermissionTo('users:store');
    }

    public function show(User $user): bool
    {
        return $user->hasPermissionTo('users:show');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('users:update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('users:delete');
    }
}
