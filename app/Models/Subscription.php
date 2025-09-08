<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
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
            'package_id' => 'integer',
            'start_date' => 'date',
            'trial_end_date' => 'date',
            'end_date' => 'date',
            'package_price' => 'decimal:4',
            'original_price' => 'decimal:4',
            'coupon_code' => 'string',
            'created_id' => 'integer',
            'paid_via' => 'string',
            'payment_transaction_id' => 'string',
            'deleted_at' => 'timestamp'
        ];
    }
}
