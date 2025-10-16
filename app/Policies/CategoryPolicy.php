<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any categories.
     */
    public function index(User $user): bool
    {
        return $user->can('categories:index') ||
            $user->can('manage_business') ||
            $user->businesses()->exists();
    }

    /**
     * Determine whether the user can view the category.
     */
    public function show(User $user, Category $category): bool
    {
        // El usuario puede ver categorías de su propio negocio
        $userBusinessIds = $user->businesses->pluck('id')->toArray();

        return $user->can('categories:show') ||
            $user->can('manage_business');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->can('categories:create') ||
            $user->can('manage_business') ||
            $user->businesses()->exists();
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can('categories:update') ||
            $user->can('manage_business') ||
            $user->businesses()->exists();
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories:delete') ||
            $user->can('manage_business') ||
            $user->businesses()->exists();
    }

    /**
     * Determine whether the user can restore the category.
     */
    public function restore(User $user, Category $category): bool
    {
        return $user->can('categories:restore') ||
            $user->can('manage_business') ||
            $user->businesses()->exists();
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
