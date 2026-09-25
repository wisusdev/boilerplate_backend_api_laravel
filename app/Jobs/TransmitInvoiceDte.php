<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Dte\DteException;
use App\Services\Dte\DtePendingException;
use App\Services\DteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Emisión automática del DTE al confirmar una reserva.
 *
 * Se despacha después de la respuesta (`dispatchAfterResponse`): quien confirma
 * la reserva, o el webhook de la pasarela, no espera al MH, y no hace falta un
 * worker de colas (producción corre con QUEUE_CONNECTION=sync). Si el MH no
 * responde, el documento queda pendiente y lo retoma `dte:retry`.
 */
class TransmitInvoiceDte implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

    public function handle(DteService $dte): void
    {
        try {
            $dte->processDte($this->invoice->fresh());
        } catch (DtePendingException $e) {
            // Firmado y guardado; `dte:retry` lo reenviará.
            Log::info('DTE automático pendiente de sello', ['invoice_id' => $this->invoice->id, 'error' => $e->getMessage()]);
        } catch (DteException $e) {
            // Datos incompletos o rechazo: se resuelve desde el back-office.
            Log::warning('DTE automático no emitido', ['invoice_id' => $this->invoice->id, 'error' => $e->getMessage()]);
        }
    }
}
