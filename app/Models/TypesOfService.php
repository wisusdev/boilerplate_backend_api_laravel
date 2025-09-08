<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypesOfService extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer',
            'packing_charge' => 'decimal:4',
            'enable_custom_fields' => 'boolean'
        ];
    }
}
