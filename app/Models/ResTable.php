<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResTable extends Model
{
    use HasFactory;

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'location_id' => 'integer',
            'name' => 'string',
            'created_by' => 'integer',
            'deleted_at' => 'timestamp'
        ];
    }
}
