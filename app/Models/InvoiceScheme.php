<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceScheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'scheme_type',
        'number_type',
        'prefix',
        'start_number',
        'invoice_count',
        'total_digits',
        'is_default',
    ];

    public function getResourceType(): string
    {
        return 'invoice-schemes';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function business_locations(): HasMany
    {
        return $this->hasMany(BusinessLocation::class, 'invoice_scheme_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'name' => 'string',
            'number_type' => 'string',
            'prefix' => 'string',
            'start_number' => 'integer',
            'invoice_count' => 'integer',
            'total_digits' => 'integer',
            'is_default' => 'boolean'
        ];
    }
}
