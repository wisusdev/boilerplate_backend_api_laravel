<?php

namespace App\Policies;

use App\Models\InvoiceScheme;
use App\Models\User;

class InvoiceSchemePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->can('invoice-schemes:index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $user->can('invoice-schemes:show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('invoice-schemes:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $user->can('invoice-schemes:update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $user->can('invoice-schemes:delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $user->can('invoice-schemes:restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $user->can('invoice-schemes:force-delete');
    }
}
