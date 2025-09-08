<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer',
            'brand_id' => 'integer',
            'category_id' => 'integer',
            'location_id' => 'integer',
            'priority' => 'integer',
            'discount_type' => 'string',
            'discount_amount' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'spg' => 'string',
            'applicable_in_cg' => 'boolean'
        ];
    }
}
