<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'gateway',
        'method',
        'amount',
        'currency_code',
        'status',
        'transaction_reference',
        'payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getResourceType(): string
    {
        return 'payments';
    }
}