<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use HasFactory;

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'name' => 'string',
            'amount' => 'double',
            'price_calculation_type' => 'string',
            'selling_price_group_id' => 'integer',
            'created_by' => 'integer'
        ];
    }
}
