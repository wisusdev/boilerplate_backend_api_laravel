<?php

namespace App\Policies;

use App\Models\Printer;
use App\Models\User;

class PrinterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('printers:index');
    }

    public function view(User $user, Printer $printer): bool
    {
        return $user->can('printers:show');
    }

    public function create(User $user): bool
    {
        return $user->can('printers:store');
    }

    public function update(User $user, Printer $printer): bool
    {
        return $user->can('printers:update');
    }

    public function delete(User $user, Printer $printer): bool
    {
        return $user->can('printers:delete');
    }
}
