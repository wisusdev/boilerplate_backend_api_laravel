<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Aviso por correo a quien administra el sitio.
 *
 * Estaba dentro de BookingController; los enlaces de pago también necesitan
 * avisar, y duplicar la consulta de roles era pedir que las dos copias se
 * separasen con el tiempo.
 */
class AdminAlerts
{
    /**
     * @param  array<string, string|null>  $details
     */
    public static function send(string $title, string $body, array $details = []): void
    {
        // Solo roles que existan: el scope role() de Spatie lanza excepción con
        // un rol inexistente.
        $roleNames = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['admin', 'superadmin'])
            ->pluck('name')
            ->all();

        if ($roleNames === []) {
            return;
        }

        $emails = User::role($roleNames, 'api')->pluck('email')->filter()->unique();

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new AdminAlertNotification($title, $body, $details));
        }
    }
}
