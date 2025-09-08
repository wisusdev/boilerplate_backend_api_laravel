<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationGroupPrice extends Model
{
    use HasFactory;

    public function selling_price_group()
    {
        return $this->belongsTo(SellingPriceGroup::class, 'price_group_id');
    }    public function variation()
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    protected function casts(): array
    {
        return [
            'variation_id' => 'integer',
            'price_group_id' => 'integer',
            'price_inc_tax' => 'decimal:4',
            'price_type' => 'string'
        ];
    }
}
