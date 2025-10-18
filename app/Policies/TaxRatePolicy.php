<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->can('tax-rates:index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates:show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('tax-rates:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates:update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates:delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates:restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaxRate $taxRate): bool
    {
        return $user->can('tax-rates:force-delete');
    }
}
