<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\InvoiceScheme;
use App\Models\User;

class InvoiceSchemePolicy
{
    /**
     * Determine whether the user can view any invoice schemes.
     */
    public function index(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:index');
    }

    /**
     * Determine whether the user can view the invoice scheme.
     */
    public function view(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $invoiceScheme->business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:show');
    }

    /**
     * Determine whether the user can create invoice schemes.
     */
    public function create(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:create');
    }

    /**
     * Determine whether the user can update the invoice scheme.
     */
    public function update(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $invoiceScheme->business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:edit');
    }

    /**
     * Determine whether the user can delete the invoice scheme.
     */
    public function delete(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $invoiceScheme->business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:delete');
    }

    /**
     * Determine whether the user can view invoice scheme statistics.
     */
    public function viewStatistics(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $invoiceScheme->business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:statistics');
    }

    /**
     * Determine whether the user can change invoice scheme status.
     */
    public function changeStatus(User $user, InvoiceScheme $invoiceScheme): bool
    {
        return $invoiceScheme->business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:change-status');
    }

    /**
     * Determine whether the user can bulk delete invoice schemes.
     */
    public function bulkDelete(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id || $user->hasPermissionTo('invoice-schemes:bulk-destroy');
    }
}
