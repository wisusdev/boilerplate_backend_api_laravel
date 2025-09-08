<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_categories') || 
               $user->can('manage_business') || 
               $user->businesses()->exists();
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        // El usuario puede ver categorías de su propio negocio
        $userBusinessIds = $user->businesses->pluck('id')->toArray();
        
        return in_array($category->business_id, $userBusinessIds) ||
               $user->can('view_categories') ||
               $user->can('manage_business');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->can('create_categories') || 
               $user->can('manage_business') || 
               $user->businesses()->exists();
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        // El usuario puede actualizar categorías de su propio negocio
        $userBusinessIds = $user->businesses->pluck('id')->toArray();
        
        return in_array($category->business_id, $userBusinessIds) || $user->can('manage_all_categories');
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        // No se puede eliminar si tiene productos o subcategorías
        if ($category->products()->exists() || $category->subcategories()->exists()) {
            return false;
        }

        // El usuario puede eliminar categorías de su propio negocio
        $userBusinessIds = $user->businesses->pluck('id')->toArray();
        
        return in_array($category->business_id, $userBusinessIds) || $user->can('manage_all_categories');
    }

    /**
     * Determine whether the user can restore the category.
     */
    public function restore(User $user, Category $category): bool
    {
        $userBusinessIds = $user->businesses->pluck('id')->toArray();
        
        return (in_array($category->business_id, $userBusinessIds) && 
                ($user->can('restore_categories') || $user->can('manage_business'))) ||
               $user->can('manage_all_categories');
    }

    /**
     * Determine whether the user can permanently delete the category.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        $userBusinessIds = $user->businesses->pluck('id')->toArray();
        
        return (in_array($category->business_id, $userBusinessIds) && 
                $user->can('force_delete_categories')) ||
               $user->can('manage_all_categories');
    }
}
