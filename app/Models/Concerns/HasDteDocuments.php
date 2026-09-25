<?php

namespace App\Models\Concerns;

use App\Models\DteDocument;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un modelo que emite DTE: una factura, una nota de crédito o débito, un
 * documento de compra. Sus DTE viven en `dte_documents` y el modelo guarda un
 * resumen del último (columnas `dte_*`) para listados y filtros.
 */
trait HasDteDocuments
{
    /** Columnas del resumen que comparten todos los dueños de DTE. */
    private static array $dteSummaryColumns = [
        'dte_type', 'dte_status', 'dte_number', 'dte_generation_code', 'dte_seal',
        'dte_environment', 'dte_submitted_at', 'dte_accepted_at', 'mh_response',
    ];

    /** Clave foránea del modelo en `dte_documents`. */
    abstract public function dteForeignKey(): string;

    public function dteDocuments(): HasMany
    {
        return $this->hasMany(DteDocument::class, $this->dteForeignKey());
    }

    /**
     * Actualiza el resumen con lo que aplique a este modelo: una factura acepta
     * además `status` (emitida, cancelada); una nota lo ignora.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function applyDteSummary(array $attrs): void
    {
        $permitidas = [...self::$dteSummaryColumns, ...$this->extraDteSummaryColumns()];
        $this->forceFill(array_intersect_key($attrs, array_flip($permitidas)))->save();
    }

    /** @return list<string> */
    protected function extraDteSummaryColumns(): array
    {
        return [];
    }
}
