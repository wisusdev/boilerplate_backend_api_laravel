<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'preferred_destinations',
        'travel_start_date',
        'travel_end_date',
        'budget_min',
        'budget_max',
        'travelers_count',
        'currency_code',
        'message',
        'status',
    ];

    protected $casts = [
        'preferred_destinations' => 'array',
        'travel_start_date' => 'date:Y-m-d',
        'travel_end_date' => 'date:Y-m-d',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'travelers_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getResourceType(): string
    {
        return 'custom_inquiries';
    }
}
