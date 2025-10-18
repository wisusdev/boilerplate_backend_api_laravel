<?php

namespace App\Policies;

use App\Models\InvoiceLayout;
use App\Models\User;

class InvoiceLayoutPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        return $user->can('invoice-layouts:index');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $user->can('invoice-layouts:show');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('invoice-layouts:create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $user->can('invoice-layouts:update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $user->can('invoice-layouts:delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $user->can('invoice-layouts:restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $user->can('invoice-layouts:force-delete');
    }
}
