<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'contact_id' => 'integer',
            'waiter_id' => 'integer',
            'table_id' => 'integer',
            'correspondent_id' => 'integer',
            'business_id' => 'integer',
            'location_id' => 'integer',
            'booking_start' => 'datetime',
            'booking_end' => 'datetime',
            'created_by' => 'integer',
            'booking_status' => 'string'
        ];
    }
}
