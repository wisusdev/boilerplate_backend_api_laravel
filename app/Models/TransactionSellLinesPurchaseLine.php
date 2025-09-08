<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSellLinesPurchaseLine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sell_line_id' => 'integer',
            'stock_adjustment_line_id' => 'integer',
            'purchase_line_id' => 'integer',
            'quantity' => 'decimal:4',
            'qty_returned' => 'decimal:4'
        ];
    }
}
