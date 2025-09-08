<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\InvoiceLayout;
use App\Models\User;

class InvoiceLayoutPolicy
{
    /**
     * Determine whether the user can view any invoice layouts.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:index');
    }

    /**
     * Determine whether the user can view the invoice layout.
     */
    public function view(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $invoiceLayout->business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:show');
    }

    /**
     * Determine whether the user can create invoice layouts.
     */
    public function create(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:create');
    }

    /**
     * Determine whether the user can update the invoice layout.
     */
    public function update(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $invoiceLayout->business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:edit');
    }

    /**
     * Determine whether the user can delete the invoice layout.
     */
    public function delete(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $invoiceLayout->business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:delete');
    }

    /**
     * Determine whether the user can view invoice layout statistics.
     */
    public function viewStatistics(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $invoiceLayout->business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:statistics');
    }

    /**
     * Determine whether the user can change invoice layout status.
     */
    public function changeStatus(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $invoiceLayout->business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:change-status');
    }

    /**
     * Determine whether the user can bulk delete invoice layouts.
     */
    public function bulkDelete(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:bulk-delete');
    }

    /**
     * Determine whether the user can duplicate the invoice layout.
     */
    public function duplicate(User $user, InvoiceLayout $invoiceLayout): bool
    {
        return $invoiceLayout->business->owner_id === $user->id || $user->hasPermissionTo('invoice-layouts:duplicate');
    }
}
