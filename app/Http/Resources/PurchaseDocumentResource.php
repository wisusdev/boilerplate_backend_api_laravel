<?php

namespace App\Http\Resources;

use App\Models\PurchaseDocument;
use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property PurchaseDocument $resource */
class PurchaseDocumentResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $d = $this->resource;

        return [
            'kind' => $d->kind,
            'number' => $d->number,
            'expense_id' => $d->expense_id,
            'proveedor_nombre' => $d->proveedor_nombre,
            'proveedor_tipo_documento' => $d->proveedor_tipo_documento,
            'proveedor_num_documento' => $d->proveedor_num_documento,
            'proveedor_nrc' => $d->proveedor_nrc,
            'proveedor_cod_actividad' => $d->proveedor_cod_actividad,
            'proveedor_nombre_comercial' => $d->proveedor_nombre_comercial,
            'proveedor_departamento' => $d->proveedor_departamento,
            'proveedor_municipio' => $d->proveedor_municipio,
            'proveedor_distrito' => $d->proveedor_distrito,
            'proveedor_direccion' => $d->proveedor_direccion,
            'proveedor_telefono' => $d->proveedor_telefono,
            'proveedor_correo' => $d->proveedor_correo,
            'retener_renta' => $d->retener_renta,
            'condicion_operacion' => $d->condicion_operacion,
            'forma_pago' => $d->forma_pago,
            'items' => $d->items,
            'total' => $d->total,
            'observaciones' => $d->observaciones,
            'dte_type' => $d->dte_type,
            'dte_status' => $d->dte_status,
            'dte_number' => $d->dte_number,
            'dte_seal' => $d->dte_seal,
            'dte_environment' => $d->dte_environment,
            'dte_accepted_at' => $d->dte_accepted_at,
            'mh_response' => $d->mh_response,
            'dte_documents' => DteDocumentSummary::list($d),
            'created_at' => $d->created_at,
        ];
    }
}
