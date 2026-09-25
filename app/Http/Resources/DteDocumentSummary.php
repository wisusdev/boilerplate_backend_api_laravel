<?php

namespace App\Http\Resources;

use App\Models\Contracts\DteOwner;
use App\Models\DteDocument;

/**
 * Historial de DTE de una factura o nota, del más reciente al más antiguo. Ni
 * el JSON firmado ni la firma salen por aquí: se descargan aparte.
 */
final class DteDocumentSummary
{
    /** @return list<array<string, mixed>> */
    public static function list(DteOwner $owner): array
    {
        if (! $owner->relationLoaded('dteDocuments')) {
            return [];
        }

        return $owner->dteDocuments->sortByDesc('id')->values()->map(fn (DteDocument $d) => [
            'id' => $d->id,
            'tipo_dte' => $d->tipo_dte,
            'ambiente' => $d->ambiente,
            'numero_control' => $d->numero_control,
            'codigo_generacion' => $d->codigo_generacion,
            'estado' => $d->estado,
            'sello_recibido' => $d->sello_recibido,
            'intentos' => $d->intentos,
            'ultimo_error' => $d->ultimo_error,
            'transmitido_at' => $d->transmitido_at,
            'contingencia_id' => $d->contingencia_id,
            'entregado_at' => $d->entregado_at,
            'entregado_a' => $d->entregado_a,
            'entregado_con_sello' => $d->entregado_con_sello,
            'created_at' => $d->created_at,
            'invalidacion' => $d->relationLoaded('invalidaciones') && ($i = $d->invalidaciones->sortByDesc('id')->first())
                ? [
                    'tipo_anulacion' => $i->tipo_anulacion,
                    'motivo' => $i->motivo,
                    'estado' => $i->estado,
                    'codigo_generacion_r' => $i->codigo_generacion_r,
                    'solicita_nombre' => $i->solicita_nombre,
                    'sello_recibido' => $i->sello_recibido,
                    'ultimo_error' => $i->ultimo_error,
                    'transmitido_at' => $i->transmitido_at,
                ]
                : null,
        ])->all();
    }
}
