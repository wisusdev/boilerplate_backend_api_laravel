<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Printer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'connection_type',
        'capability_profile',
        'char_per_line',
        'ip_address',
        'port',
        'path',
        'created_by',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'name' => 'string',
            'connection_type' => 'string',
            'capability_profile' => 'string',
            'char_per_line' => 'string',
            'ip_address' => 'string',
            'port' => 'string',
            'path' => 'string',
            'created_by' => 'integer',
        ];
    }
}
