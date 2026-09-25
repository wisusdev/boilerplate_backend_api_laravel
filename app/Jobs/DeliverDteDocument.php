<?php

namespace App\Jobs;

use App\Models\DteDocument;
use App\Services\Dte\DteDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Entrega automática del DTE a su receptor, después de la respuesta (no hace
 * falta worker de colas). Se vuelve a comprobar el estado al ejecutarse: entre
 * el despacho y la entrega el documento pudo sellarse o entregarse ya.
 */
class DeliverDteDocument implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public readonly int $documentId) {}

    public function handle(DteDelivery $delivery): void
    {
        $doc = DteDocument::find($this->documentId);
        if (! $doc) {
            return;
        }

        $pendiente = match ($doc->estado) {
            DteDocument::TRANSMITTED => ! $doc->entregado_con_sello,
            DteDocument::CONTINGENCY => $doc->entregado_at === null,
            default => false,
        };
        if (! $pendiente) {
            return;
        }

        try {
            $delivery->deliver($doc);
        } catch (\Throwable $e) {
            // Un correo que no sale no afecta al DTE: se puede reenviar desde el back-office.
            Log::warning('Entrega del DTE fallida', ['dte_document_id' => $doc->id, 'error' => $e->getMessage()]);
        }
    }
}
