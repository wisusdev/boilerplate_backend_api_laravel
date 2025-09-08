<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsUserSalesTarget extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'target_start' => 'decimal:4',
            'target_end' => 'decimal:4',
            'commission_percent' => 'decimal:4'
        ];
    }
}
