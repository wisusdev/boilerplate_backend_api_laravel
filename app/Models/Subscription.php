<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'package_id',
        'start_date',
        'end_date',
        'trial_ends_at',
        'package_price',
        'package_details',
        'created_by',
        'payment_method',
        'payment_transaction_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'cancel';
    }
}
