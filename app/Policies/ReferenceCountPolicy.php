<?php

namespace App\Policies;

use App\Models\ReferenceCount;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReferenceCountPolicy
{
    /**
     * Determine whether the user can view any reference counts.
     */
    public function viewAny(User $user): bool
    {
        // El usuario debe ser el propietario del negocio o tener permisos de administrador
        return $user->hasPermissionTo('reference_counts:index');
    }

    /**
     * Determine whether the user can view the reference count.
     */
    public function view(User $user, ReferenceCount $referenceCount): Response
    {
        // El usuario debe ser el propietario del negocio asociado al contador de referencia
        return $user->id === $referenceCount->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver este contador de referencia.');
    }

    /**
     * Determine whether the user can create reference counts.
     */
    public function create(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para crear contadores de referencia en este negocio.');
    }

    /**
     * Determine whether the user can update the reference count.
     */
    public function update(User $user, ReferenceCount $referenceCount): Response
    {
        // El usuario debe ser el propietario del negocio asociado al contador de referencia
        return $user->id === $referenceCount->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para actualizar este contador de referencia.');
    }

    /**
     * Determine whether the user can delete the reference count.
     */
    public function delete(User $user, ReferenceCount $referenceCount): Response
    {
        // El usuario debe ser el propietario del negocio asociado al contador de referencia
        return $user->id === $referenceCount->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para eliminar este contador de referencia.');
    }
}
