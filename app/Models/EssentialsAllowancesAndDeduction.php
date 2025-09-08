<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsAllowancesAndDeduction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'description' => 'string',
            'amount' => 'decimal:4',
            'applicable_date' => 'date'
        ];
    }
}
