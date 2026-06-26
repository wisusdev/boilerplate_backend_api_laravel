<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'rate_to_usd',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate_to_usd' => 'decimal:8',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Garantiza que exista una sola moneda marcada como predeterminada.
        static::saved(function (self $currency) {
            if ($currency->is_default) {
                static::query()
                    ->whereKeyNot($currency->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function getResourceType(): string
    {
        return 'currencies';
    }
}