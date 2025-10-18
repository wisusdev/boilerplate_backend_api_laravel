<?php

namespace App\Policies;

use App\Models\BusinessLocation;
use App\Models\User;

class BusinessLocationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->can('business_locations:index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, BusinessLocation $businessLocation): bool
    {
        return $user->can('business_locations:show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('business_locations:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BusinessLocation $businessLocation): bool
    {
        return $user->can('business_locations:update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BusinessLocation $businessLocation): bool
    {
        return $user->can('business_locations:delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BusinessLocation $businessLocation): bool
    {
        return $user->can('business_locations:restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BusinessLocation $businessLocation): bool
    {
        return $user->can('business_locations:force-delete');
    }
}
