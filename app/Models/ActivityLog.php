<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'log_name' => 'string',
            'subject_id' => 'integer',
            'subject_type' => 'string',
            'event' => 'string',
            'business_id' => 'integer',
            'causer_id' => 'integer',
            'causer_type' => 'string'
        ];
    }
}
