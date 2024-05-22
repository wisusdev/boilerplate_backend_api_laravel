<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'max_users',
        'interval',
        'interval_count',
        'price',
        'trial_days',
        'active',
        'created_by',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isNotActive(): bool
    {
        return !$this->active;
    }

    public function isDayInterval(): bool
    {
        return $this->interval === 'day';
    }

    public function isWeekInterval(): bool
    {
        return $this->interval === 'week';
    }

    public function isMonthInterval(): bool
    {
        return $this->interval === 'month';
    }

    public function isYearInterval(): bool
    {
        return $this->interval === 'year';
    }

    public function isTrial(): bool
    {
        return $this->trial_days > 0;
    }
}
