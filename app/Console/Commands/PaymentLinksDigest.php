<?php

namespace App\Console\Commands;

use App\Notifications\PaymentLinksDigestNotification;
use App\Services\PaymentLinkService;
use App\Support\AdminAlerts;
use Illuminate\Console\Command;

/**
 * Resumen diario de los enlaces de pago del banco que siguen abiertos.
 *
 * No envía nada si no hay ninguno: un correo vacío es ruido, no información.
 */
class PaymentLinksDigest extends Command
{
    protected $signature = 'payment-links:digest';

    protected $description = 'Envía al back-office el resumen diario de enlaces de pago del banco pendientes.';

    public function handle(PaymentLinkService $service): int
    {
        $datos = $service->digestData();

        if ($datos['counts'] === []) {
            $this->info('Sin enlaces abiertos: no se envía resumen.');

            return self::SUCCESS;
        }

        AdminAlerts::notifyAll(new PaymentLinksDigestNotification(
            $datos['counts'],
            $datos['reported'],
            $datos['expiring_soon'],
        ));

        $this->info('Resumen enviado: '.array_sum($datos['counts']).' enlace(s) abierto(s).');

        return self::SUCCESS;
    }
}
