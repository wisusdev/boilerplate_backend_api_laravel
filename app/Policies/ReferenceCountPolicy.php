<?php

namespace App\Policies;

use App\Models\ReferenceCount;
use App\Models\User;

class ReferenceCountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->can('reference_counts:index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, ReferenceCount $referenceCount): bool
    {
        return $user->can('reference_counts:show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('reference_counts:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReferenceCount $referenceCount): bool
    {
        return $user->can('reference_counts:update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReferenceCount $referenceCount): bool
    {
        return $user->can('reference_counts:delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReferenceCount $referenceCount): bool
    {
        return $user->can('reference_counts:restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReferenceCount $referenceCount): bool
    {
        return $user->can('reference_counts:force-delete');
    }
}
