<?php

namespace App\Console\Commands;

use App\Services\PaymentLinkService;
use Illuminate\Console\Command;

/**
 * Barre los enlaces de pago del banco que ya cumplieron su plazo sin usarse.
 *
 * No anula nada en el banco (no podemos): solo deja de esperar. Ver
 * `PaymentLinkService::expireDueLinks()` y PAGO-ENLACE-BAC.md §6.
 */
class ExpirePaymentLinks extends Command
{
    protected $signature = 'payment-links:expire';

    protected $description = 'Marca como caducados los enlaces de pago del banco vencidos sin usar.';

    public function handle(PaymentLinkService $service): int
    {
        $resultado = $service->expireDueLinks();
        $expirados = $resultado['expired'];

        if ($expirados === []) {
            $this->info('Sin enlaces vencidos.');

            return self::SUCCESS;
        }

        $this->info(count($expirados).' enlace(s) caducado(s), '.$resultado['released'].' reserva(s) liberada(s).');

        foreach ($expirados as $fila) {
            $this->line('  '.$fila['reference'].' · reserva #'.($fila['booking_id'] ?? '?')
                .' · '.$fila['amount'].' '.$fila['currency_code']
                .($fila['seat_released'] ? ' · asiento liberado' : ''));
        }

        return self::SUCCESS;
    }
}
