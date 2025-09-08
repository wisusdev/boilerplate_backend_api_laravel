<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRack extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'integer',
            'product_id' => 'integer',
            'rack' => 'string',
            'row' => 'string',
            'position' => 'string'
        ];
    }
}
