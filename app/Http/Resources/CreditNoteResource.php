<?php

namespace App\Http\Resources;

use App\Models\CreditNote;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property CreditNote $resource */
class CreditNoteResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $n = $this->resource;

        return [
            'invoice_id' => $n->invoice_id,
            'kind' => $n->kind,
            'number' => $n->number,
            'motivo' => $n->motivo,
            'subtotal' => $n->subtotal,
            'iva' => $n->iva,
            'total' => $n->total,
            'dte_type' => $n->dte_type,
            'dte_status' => $n->dte_status,
            'dte_number' => $n->dte_number,
            'dte_generation_code' => $n->dte_generation_code,
            'dte_seal' => $n->dte_seal,
            'dte_environment' => $n->dte_environment,
            'dte_accepted_at' => $n->dte_accepted_at,
            'mh_response' => $n->mh_response,
            'items' => $n->relationLoaded('items')
                ? $n->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'total' => $i->total,
                ])->all()
                : [],
            'dte_documents' => DteDocumentSummary::list($n),
            'created_at' => $n->created_at,
        ];
    }
}
