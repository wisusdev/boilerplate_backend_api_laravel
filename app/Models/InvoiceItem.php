<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concepto de una factura.
 *
 * La descripción y el precio son un snapshot del momento de la emisión: si el
 * tour cambia de precio después, la factura ya emitida no se altera. Las
 * referencias al catálogo son informativas y se anulan si el producto se borra.
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total',
        'tour_id',
        'transport_vehicle_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function transportVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class);
    }
}
