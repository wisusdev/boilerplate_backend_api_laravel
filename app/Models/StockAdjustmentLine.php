<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLine extends Model
{
    use HasFactory;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }    public function variation()
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    protected function casts(): array
    {
        return [
            'transaction_id' => 'integer',
            'product_id' => 'integer',
            'variation_id' => 'integer',
            'quantity' => 'decimal:4',
            'secondary_unit_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'removed_purchase_line' => 'integer',
            'lot_no_line_id' => 'integer'
        ];
    }
}
