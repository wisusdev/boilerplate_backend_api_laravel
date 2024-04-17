<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_token',
        'login_at',
        'browser',
        'os',
        'ip',
        'country',
    ];

    protected $casts = [
        'login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLoginAtAttribute($value): string
    {
        return Carbon::parse($value)->diffForHumans();
    }

    public function getResouceType(): string
    {
        return 'device_info';
    }
}
