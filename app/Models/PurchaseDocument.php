<?php

namespace App\Models;

use App\Models\Concerns\HasDteDocuments;
use App\Models\Contracts\DteOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento que vamosPues emite al comprar: factura de sujeto excluido (14) o
 * comprobante de retención (07). El proveedor es el receptor del DTE.
 */
class PurchaseDocument extends Model implements DteOwner
{
    use HasDteDocuments;

    /** Factura de sujeto excluido. */
    public const FSE = 'fse';

    /** Comprobante de retención. */
    public const CR = 'cr';

    protected $fillable = [
        'kind',
        'number',
        'expense_id',
        'proveedor_nombre',
        'proveedor_tipo_documento',
        'proveedor_num_documento',
        'proveedor_nrc',
        'proveedor_cod_actividad',
        'proveedor_nombre_comercial',
        'proveedor_departamento',
        'proveedor_municipio',
        'proveedor_distrito',
        'proveedor_direccion',
        'proveedor_telefono',
        'proveedor_correo',
        'retener_renta',
        'condicion_operacion',
        'forma_pago',
        'items',
        'total',
        'observaciones',
        'created_by',
        'dte_type',
        'dte_status',
        'dte_number',
        'dte_generation_code',
        'dte_seal',
        'dte_environment',
        'dte_submitted_at',
        'dte_accepted_at',
        'mh_response',
    ];

    protected $casts = [
        'items' => 'array',
        'retener_renta' => 'boolean',
        'condicion_operacion' => 'integer',
        'total' => 'decimal:2',
        'mh_response' => 'array',
        'dte_submitted_at' => 'datetime',
        'dte_accepted_at' => 'datetime',
    ];

    public function dteForeignKey(): string
    {
        return 'purchase_document_id';
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** 14 sujeto excluido, 07 comprobante de retención. */
    public function tipoDte(): string
    {
        return $this->kind === self::CR ? '07' : '14';
    }

    public function getResourceType(): string
    {
        return 'purchase-documents';
    }
}
