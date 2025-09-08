<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsAttendance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'business_id' => 'integer',
            'clock_in_time' => 'datetime',
            'clock_out_time' => 'datetime',
            'essentials_shift_id' => 'integer',
            'ip_address' => 'string'
        ];
    }
}
