<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenceCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'ref_type',
        'ref_count'
    ];

    public function getResourceType(): string
    {
        return 'reference_counts';
    }

    protected function casts(): array
    {
        return [
            'ref_type' => 'string',
            'ref_count' => 'integer',
            'business_id' => 'integer'
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
