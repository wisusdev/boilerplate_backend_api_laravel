<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Variation;

class VariationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->can('variations:index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, Variation $variation): bool
    {
        return $user->can('variations:show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('variations:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Variation $variation): bool
    {
        return $user->can('variations:update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Variation $variation): bool
    {
        return $user->can('variations:delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Variation $variation): bool
    {
        return $user->can('variations:restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Variation $variation): bool
    {
        return $user->can('variations:force-delete');
    }
}
