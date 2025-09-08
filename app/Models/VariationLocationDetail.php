<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationLocationDetail extends Model
{
    use HasFactory;

    public function business_location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }    public function variation()
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variation_id' => 'integer',
            'variation_id' => 'integer',
            'location_id' => 'integer',
            'qty_available' => 'decimal:4'
        ];
    }
}
