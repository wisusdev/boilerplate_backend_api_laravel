<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Illuminate\Notifications\Notification as NotificationBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Aviso por correo a quien administra el sitio.
 *
 * Un único punto para resolver "quién es admin" evita que cada sitio nuevo que
 * necesite avisar al back-office (pagos, enlaces del banco, reservas) repita la
 * consulta de roles y acabe divergiendo con el tiempo.
 */
class AdminAlerts
{
    /**
     * Aviso simple de una línea por dato (título + cuerpo + detalles planos).
     *
     * @param  array<string, string|null>  $details
     */
    public static function send(string $title, string $body, array $details = []): void
    {
        self::notifyAll(new AdminAlertNotification($title, $body, $details));
    }

    /**
     * Envía cualquier notificación (estructura propia, no solo texto plano) a
     * todo el back-office. Para avisos que necesitan más forma que "etiqueta:
     * valor" — un resumen con varias secciones, una tabla, etc.
     */
    public static function notifyAll(NotificationBase $notification): void
    {
        foreach (self::recipients() as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * Correos de admin/superadmin. Solo roles que existan: el scope role() de
     * Spatie lanza excepción con un rol inexistente.
     *
     * @return Collection<int, string>
     */
    public static function recipients(): Collection
    {
        $roleNames = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['admin', 'superadmin'])
            ->pluck('name')
            ->all();

        if ($roleNames === []) {
            return collect();
        }

        return User::role($roleNames, 'api')->pluck('email')->filter()->unique()->values();
    }
}
