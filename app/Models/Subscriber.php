<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;

    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'email',
        'name',
        'source',
        'locale',
        'status',
        'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeStatus($query, $value)
    {
        return $query->where('status', $value);
    }

    public function scopeSearch($query, $value)
    {
        return $query->where(function ($q) use ($value) {
            $q->where('email', 'LIKE', "%{$value}%")
                ->orWhere('name', 'LIKE', "%{$value}%");
        });
    }

    public function getResourceType(): string
    {
        return 'subscribers';
    }
}
