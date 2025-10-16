<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UnitPolicy
{
    /**
     * Determine whether the user can view any units.
     */
    public function index(User $user, Business $business): bool
    {
		// El usuario puede ver las unidades de su propio negocio
        return $user->can('units:index', [$business]) ||
	        $user->businesses()->exists();
    }

    /**
     * Determine whether the user can view the unit.
     */
    public function show(User $user, Unit $unit): bool
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->can('units:show', [$unit->business]) ||
	        $user->businesses()->exists();
    }

    /**
     * Determine whether the user can create units.
     */
    public function create(User $user, Business $business): bool
    {
	    return $user->can('units:create', [$business]) ||
		    $user->businesses()->exists();
    }

    /**
     * Determine whether the user can update the unit.
     */
    public function update(User $user, Unit $unit): bool
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->can('units:update', [$unit->business]) ||
	        $user->businesses()->exists();
    }

    /**
     * Determine whether the user can delete the unit.
     */
    public function delete(User $user, Unit $unit): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        // y la unidad no debe tener productos asociados
        if ($user->id !== $unit->business->user_id && !$user->hasRole('admin')) {
            return Response::deny('No tienes permisos para eliminar esta unidad.');
        }

        // Verificar si la unidad tiene productos asociados
        if ($unit->products()->exists()) {
            return Response::deny('No puedes eliminar una unidad que tiene productos asociados.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the unit.
     */
    public function restore(User $user, Unit $unit): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->id === $unit->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para restaurar esta unidad.');
    }

    /**
     * Determine whether the user can permanently delete the unit.
     */
    public function forceDelete(User $user, Unit $unit): Response
    {
        // Solo administradores pueden eliminar permanentemente
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('Solo los administradores pueden eliminar permanentemente las unidades.');
    }

    /**
     * Determine whether the user can view unit statistics.
     */
    public function viewStatistics(User $user, Unit $unit): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->id === $unit->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para ver las estadísticas de esta unidad.');
    }

    /**
     * Determine whether the user can manage products of the unit.
     */
    public function manageProducts(User $user, Unit $unit): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->id === $unit->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para gestionar los productos de esta unidad.');
    }

    /**
     * Determine whether the user can change the unit status.
     */
    public function changeStatus(User $user, Unit $unit): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->id === $unit->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para cambiar el estado de esta unidad.');
    }

    /**
     * Determine whether the user can bulk delete units.
     */
    public function bulkDelete(User $user, Business $business): Response
    {
        // El usuario debe ser el propietario del negocio
        return $user->id === $business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para realizar eliminaciones masivas en este negocio.');
    }

    /**
     * Determine whether the user can convert units.
     */
    public function convertUnits(User $user, Unit $unit): Response
    {
        // El usuario debe ser el propietario del negocio asociado a la unidad
        return $user->id === $unit->business->user_id || $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('No tienes permisos para realizar conversiones con esta unidad.');
    }
}
