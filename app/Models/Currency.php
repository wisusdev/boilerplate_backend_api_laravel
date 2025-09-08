<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'country',
        'country_code',
        'currency',
        'code',
        'symbol',
        'thousand_separator',
        'decimal_separator',
    ];

    /**
     * The resource type for JSON:API.
     */
    public $resourceType = 'currencies';

    /**
     * Get the businesses that use this currency.
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'currency_id');
    }

    /**
     * Get the businesses that use this currency for purchases.
     */
    public function purchase_businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'purchase_currency_id');
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'country' => 'string',
            'country_code' => 'string',
            'currency' => 'string',
            'code' => 'string',
            'symbol' => 'string',
            'thousand_separator' => 'string',
            'decimal_separator' => 'string'
        ];
    }
}
