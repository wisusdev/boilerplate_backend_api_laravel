<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->hasPermissionTo('products:index') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, Product $product): bool
    {
        // El usuario puede ver el producto si tiene permisos o si pertenece a su negocio
        return $user->hasPermissionTo('products:show') ||
               $user->hasRole('super-admin') ||
               $this->belongsToUserBusiness($user, $product);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Business $business): bool
    {
        return $user->hasPermissionTo('products:create') ||
               $user->hasRole('super-admin') ||
               $user->businesses()->where('businesses.id', $business->id)->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('products:update') ||
               $user->hasRole('super-admin') ||
               $this->belongsToUserBusiness($user, $product);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('products:delete') ||
               $user->hasRole('super-admin') ||
               $this->belongsToUserBusiness($user, $product);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('products:restore') ||
               $user->hasRole('super-admin') ||
               $this->belongsToUserBusiness($user, $product);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Check if the product belongs to any business owned by the user.
     */
    private function belongsToUserBusiness(User $user, Product $product): bool
    {
        return $user->businesses()->where('businesses.id', $product->business_id)->exists();
    }
}
