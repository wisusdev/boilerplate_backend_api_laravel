<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BrandPolicy
{
    /**
     * Determine whether the user can view any brands.
     */
    public function viewAny(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio o tener permisos de administrador
        return $user->id === $business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver las marcas de este negocio.');
    }

    /**
     * Determine whether the user can view the brand.
     */
    public function view(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver esta marca.');
    }

    /**
     * Determine whether the user can create brands.
     */
    public function create(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para crear marcas en este negocio.');
    }

    /**
     * Determine whether the user can update the brand.
     */
    public function update(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para actualizar esta marca.');
    }

    /**
     * Determine whether the user can delete the brand.
     */
    public function delete(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para eliminar esta marca.');
    }

    /**
     * Determine whether the user can restore the brand.
     */
    public function restore(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para restaurar esta marca.');
    }

    /**
     * Determine whether the user can permanently delete the brand.
     */
    public function forceDelete(User $user, Brand $brand): Response
    {
        // Solo administradores pueden eliminar permanentemente
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('Solo los administradores pueden eliminar permanentemente las marcas.');
    }

    /**
     * Determine whether the user can view brand statistics.
     */
    public function viewStatistics(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver las estadísticas de esta marca.');
    }

    /**
     * Determine whether the user can manage products of the brand.
     */
    public function manageProducts(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para gestionar los productos de esta marca.');
    }

    /**
     * Determine whether the user can change the brand status.
     */
    public function changeStatus(User $user, Brand $brand): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la marca
        return $user->id === $brand->business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para cambiar el estado de esta marca.');
    }

    /**
     * Determine whether the user can bulk delete brands.
     */
    public function bulkDelete(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->owner_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para realizar eliminaciones masivas en este negocio.');
    }
}
