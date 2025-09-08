<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSellLine extends Model
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
            'quantity_returned' => 'decimal:4',
            'unit_price_before_discount' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_discount_amount' => 'decimal:4',
            'unit_price_inc_tax' => 'decimal:4',
            'item_tax' => 'decimal:4',
            'tax_id' => 'integer',
            'discount_id' => 'integer',
            'lot_no_line_id' => 'integer',
            'so_line_id' => 'integer',
            'so_quantity_invoiced' => 'decimal:4',
            'res_service_staff_id' => 'integer',
            'res_line_order_status' => 'string',
            'parent_sell_line_id' => 'integer',
            'children_type' => 'string',
            'sub_unit_id' => 'integer'
        ];
    }
}
