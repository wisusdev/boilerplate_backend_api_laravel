<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportBookingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'pickup_location',
        'dropoff_location',
        'rental_type',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
