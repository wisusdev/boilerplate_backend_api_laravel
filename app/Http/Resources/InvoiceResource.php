<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $inv = $this->resource;

        return [
            'booking_id' => $inv->booking_id,
            'amount' => $inv->amount,
            'currency_code' => $inv->currency_code,
            'status' => $inv->status,
            'issued_at' => $inv->issued_at,
            // DTE
            'dte_type' => $inv->dte_type,
            'dte_number' => $inv->dte_number,
            'dte_generation_code' => $inv->dte_generation_code,
            'dte_seal' => $inv->dte_seal,
            'dte_code' => $inv->dte_seal, // backward compat: el "sello" MH
            'dte_status' => $inv->dte_status,
            'dte_environment' => $inv->dte_environment,
            'dte_submitted_at' => $inv->dte_submitted_at,
            'dte_accepted_at' => $inv->dte_accepted_at,
            // Receptor snapshot
            'receptor_name' => $inv->receptor_name,
            'receptor_document' => $inv->receptor_document,
            'receptor_document_type' => $inv->receptor_document_type,
            'receptor_nrc' => $inv->receptor_nrc,
            'receptor_cod_actividad' => $inv->receptor_cod_actividad,
            'receptor_nombre_comercial' => $inv->receptor_nombre_comercial,
            'receptor_departamento' => $inv->receptor_departamento,
            'receptor_municipio' => $inv->receptor_municipio,
            'receptor_distrito' => $inv->receptor_distrito,
            'receptor_direccion' => $inv->receptor_direccion,
            'receptor_telefono' => $inv->receptor_telefono,
            'receptor_agente_retencion' => $inv->receptor_agente_retencion,
            'receptor_email' => $inv->receptor_email,
            'notes' => $inv->notes,
            'number' => $inv->number,
            'is_manual' => $inv->isManual(),
            // Conceptos: los tiene la factura manual; una nacida de reserva va vacía.
            'items' => $inv->relationLoaded('items')
                ? $inv->items->map(fn ($i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'quantity' => (int) $i->quantity,
                    'unit_price' => $i->unit_price,
                    'total' => $i->total,
                    'tour_id' => $i->tour_id,
                    'transport_vehicle_id' => $i->transport_vehicle_id,
                ])->all()
                : [],
            // Booking context (if loaded)
            'booking_type' => $inv->relationLoaded('booking') ? $inv->booking?->booking_type : null,
            'tour_title' => $inv->relationLoaded('booking') && $inv->booking?->booking_type === 'tour'
                ? $inv->booking->bookable?->title : null,
            'vehicle_title' => $inv->relationLoaded('booking') && $inv->booking?->booking_type === 'transport'
                ? $inv->booking->bookable?->title : null,
            // Historial de DTE (el último manda): rechazados y el vigente.
            'dte_documents' => DteDocumentSummary::list($inv),
            // MH response (admin only or for error debugging)
            'mh_response' => $inv->mh_response,
            'created_at' => $inv->created_at,
            'updated_at' => $inv->updated_at,
        ];
    }
}
