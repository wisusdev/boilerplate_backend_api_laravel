<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Línea de una nota de crédito o débito, sin IVA. */
class CreditNoteItem extends Model
{
    protected $fillable = [
        'credit_note_id',
        'description',
        'quantity',
        'unit_price',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];
}
