<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingPriceGroup extends Model
{
    use HasFactory;

    public function variation_group_prices()
    {
        return $this->hasMany(VariationGroupPrice::class, 'price_group_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'timestamp'
        ];
    }
}
