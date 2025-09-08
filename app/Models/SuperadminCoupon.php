<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminCoupon extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'coupon_code' => 'string',
            'discount_type' => 'string',
            'discount' => 'decimal:2',
            'expiry_date' => 'date',
            'applied_on_packages' => 'string',
            'applied_on_business' => 'string',
            'is_active' => 'boolean'
        ];
    }
}
