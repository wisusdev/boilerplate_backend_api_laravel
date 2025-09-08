<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    /**
     * Determine whether the user can view any currencies.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('currencies:index') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view the currency.
     */
    public function view(User $user, Currency $currency): bool
    {
        return $user->can('currencies:show') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can create currencies.
     */
    public function create(User $user): bool
    {
        return $user->can('currencies:create') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can update the currency.
     */
    public function update(User $user, Currency $currency): bool
    {
        return $user->can('currencies:update') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can delete the currency.
     */
    public function delete(User $user, Currency $currency): bool
    {
        return $user->can('currencies:delete') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can restore the currency.
     */
    public function restore(User $user, Currency $currency): bool
    {
        return $user->can('currencies:restore') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can permanently delete the currency.
     */
    public function forceDelete(User $user, Currency $currency): bool
    {
        return $user->can('currencies:force_delete') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view currency statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->can('currencies:statistics') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can bulk delete currencies.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can('currencies:delete') || 
               $user->can('manage_system') || 
               $user->hasRole('super-admin');
    }
}
