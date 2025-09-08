<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Models\Variation;
use Illuminate\Auth\Access\Response;

class VariationPolicy
{
    /**
     * Determine whether the user can view any variations.
     */
    public function viewAny(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio o tener permisos de administrador
        return $user->id === $business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver las variaciones de este negocio.');
    }

    /**
     * Determine whether the user can view the variation.
     */
    public function view(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver esta variación.');
    }

    /**
     * Determine whether the user can create variations.
     */
    public function create(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para crear variaciones en este negocio.');
    }

    /**
     * Determine whether the user can update the variation.
     */
    public function update(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para actualizar esta variación.');
    }

    /**
     * Determine whether the user can delete the variation.
     */
    public function delete(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para eliminar esta variación.');
    }

    /**
     * Determine whether the user can restore the variation.
     */
    public function restore(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para restaurar esta variación.');
    }

    /**
     * Determine whether the user can permanently delete the variation.
     */
    public function forceDelete(User $user, Variation $variation): Response
    {
        // Solo administradores pueden eliminar permanentemente
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('Solo los administradores pueden eliminar permanentemente las variaciones.');
    }

    /**
     * Determine whether the user can view variation statistics.
     */
    public function viewStatistics(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver las estadísticas de esta variación.');
    }

    /**
     * Determine whether the user can update stock of the variation.
     */
    public function updateStock(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para actualizar el stock de esta variación.');
    }

    /**
     * Determine whether the user can change the variation status.
     */
    public function changeStatus(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para cambiar el estado de esta variación.');
    }

    /**
     * Determine whether the user can update price of the variation.
     */
    public function updatePrice(User $user, Variation $variation): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la variación
        return $user->id === $variation->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para actualizar el precio de esta variación.');
    }

    /**
     * Determine whether the user can bulk update variations.
     */
    public function bulkUpdate(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para realizar actualizaciones masivas en este negocio.');
    }

    /**
     * Determine whether the user can bulk delete variations.
     */
    public function bulkDelete(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para realizar eliminaciones masivas en este negocio.');
    }
}
