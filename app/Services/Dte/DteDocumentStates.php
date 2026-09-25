<?php

namespace App\Services\Dte;

use App\Models\DteDocument;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transiciones de estado de un DTE, siempre junto con el resumen `dte_*` de su
 * factura. Las usan la transmisión en línea, la contingencia y el lote.
 */
final class DteDocumentStates
{
    /** @param  array<string, mixed>  $body */
    public static function transmitted(DteDocument $doc, string $sello, array $body, array $extra = []): void
    {
        DB::transaction(function () use ($doc, $sello, $body, $extra) {
            $doc->update([
                'estado' => DteDocument::TRANSMITTED,
                'sello_recibido' => $sello,
                'fh_procesamiento' => $body['fhProcesamiento'] ?? null,
                'mh_response' => $body,
                'ultimo_error' => null,
                'transmitido_at' => now(),
                ...$extra,
            ]);
            $doc->invoice()->update([
                'dte_status' => Invoice::DTE_ACCEPTED,
                'dte_seal' => $sello,
                'mh_response' => json_encode($body),
                'status' => 'issued',
                'dte_accepted_at' => now(),
            ]);
        });
    }

    /** @param  array<string, mixed>  $body */
    public static function rejected(DteDocument $doc, string $mensaje, array $body): void
    {
        DB::transaction(function () use ($doc, $mensaje, $body) {
            $doc->update([
                'estado' => DteDocument::REJECTED,
                'mh_response' => $body,
                'ultimo_error' => $mensaje,
            ]);
            $doc->invoice()->update([
                'dte_status' => Invoice::DTE_REJECTED,
                'mh_response' => json_encode($body),
            ]);
        });
        Log::warning('DTE rechazado por el MH', ['dte_document_id' => $doc->id, 'response' => $body]);
    }

    /** @param  array<string, mixed>|null  $body */
    public static function pending(DteDocument $doc, string $error, ?array $body = null): void
    {
        $doc->update(['ultimo_error' => $error, 'mh_response' => $body]);
        $doc->invoice()->update([
            'dte_status' => Invoice::DTE_PENDING,
            'mh_response' => json_encode($body ?? ['error' => $error]),
        ]);
        Log::warning('DTE pendiente de sello', ['dte_document_id' => $doc->id, 'error' => $error]);
    }
}
