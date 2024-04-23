<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

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

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLoginAtAttribute($value): string
    {
        return Date::parse($value)->format('Y-m-d H:i:s');
    }

    public function getResourceType(): string
    {
        return 'device_info';
    }
}
