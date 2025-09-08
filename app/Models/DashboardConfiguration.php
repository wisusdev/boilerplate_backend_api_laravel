<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardConfiguration extends Model
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
            'created_by' => 'integer',
            'name' => 'string',
            'color' => 'string'
        ];
    }
}
