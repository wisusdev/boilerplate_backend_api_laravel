<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'available_date',
        'capacity_override',
        'is_closed',
    ];

    protected $casts = [
        'available_date' => 'date:Y-m-d',
        'capacity_override' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function getResourceType(): string
    {
        return 'tour_availabilities';
    }
}