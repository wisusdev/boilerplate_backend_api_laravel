<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\BusinessLocation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class BusinessLocationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any business locations.
     */
    public function viewAny(User $user, Business $business): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:index');
    }

    /**
     * Determine whether the user can view the business location.
     */
    public function view(User $user, Business $business, BusinessLocation $location): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:show');
    }

    /**
     * Determine whether the user can create business locations.
     */
    public function create(User $user, Business $business): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:create');
    }

    /**
     * Determine whether the user can update the business location.
     */
    public function update(User $user, BusinessLocation $location): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        $business = Business::find($location->business_id);
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:update');
    }

    /**
     * Determine whether the user can delete the business location.
     */
    public function delete(User $user, BusinessLocation $location): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        $business = Business::find($location->business_id);
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:delete');
    }

    /**
     * Determine whether the user can restore the business location.
     */
    public function restore(User $user, BusinessLocation $location): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        $business = Business::find($location->business_id);
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:restore');
    }

    /**
     * Determine whether the user can permanently delete the business location.
     */
    public function forceDelete(User $user, BusinessLocation $location): Response|bool
    {
        // Verificar si el usuario es propietario del negocio o tiene permiso
        $business = Business::find($location->business_id);
        return $user->id === $business->owner_id || $user->hasPermissionTo('business_locations:delete');
    }
}
