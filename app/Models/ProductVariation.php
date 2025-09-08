<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    use HasFactory;

    public function variations()
    {
        return $this->hasMany(Variation::class, 'product_variation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'variation_template_id' => 'integer',
            'name' => 'string',
            'product_id' => 'integer',
            'is_dummy' => 'boolean'
        ];
    }
}
