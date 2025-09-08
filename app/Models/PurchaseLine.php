<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseLine extends Model
{
    use HasFactory;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }    public function tax_rate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_id');
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
            'pp_without_discount' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'purchase_price' => 'decimal:4',
            'purchase_price_inc_tax' => 'decimal:4',
            'item_tax' => 'decimal:4',
            'tax_id' => 'integer',
            'purchase_requisition_line_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'quantity_sold' => 'decimal:4',
            'quantity_adjusted' => 'decimal:4',
            'quantity_returned' => 'decimal:4',
            'po_quantity_purchased' => 'decimal:4',
            'mfg_quantity_used' => 'decimal:4',
            'mfg_date' => 'date',
            'exp_date' => 'date',
            'lot_number' => 'string',
            'sub_unit_id' => 'integer'
        ];
    }
}
