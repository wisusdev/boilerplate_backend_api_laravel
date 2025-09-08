<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashDenomination extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'amount' => 'decimal:4',
            'total_count' => 'integer',
            'model_type' => 'string'
        ];
    }
}
