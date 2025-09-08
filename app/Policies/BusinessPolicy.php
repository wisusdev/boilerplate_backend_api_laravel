<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('businesses:index') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Business $business): bool
    {
        // El usuario puede ver el negocio si es el propietario o tiene permisos
        return $user->hasPermissionTo('businesses:show') ||
               $user->hasRole('super-admin') ||
               $business->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('businesses:create') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Business $business): bool
    {
        // Solo el propietario o usuarios con permisos pueden actualizar
        return $user->hasPermissionTo('businesses:update') ||
               $user->hasRole('super-admin') ||
               $business->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Business $business): bool
    {
        // Solo el propietario o superadmin pueden eliminar
        return $user->hasRole('super-admin') ||
               ($business->owner_id === $user->id && $user->hasPermissionTo('businesses:delete'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Business $business): bool
    {
        return $user->hasRole('super-admin') ||
               ($business->owner_id === $user->id && $user->hasPermissionTo('businesses:restore'));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Business $business): bool
    {
        return $user->hasRole('super-admin');
    }
}
