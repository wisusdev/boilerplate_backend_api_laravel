<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsLeaveType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'leave_type' => 'string',
            'max_leave_count' => 'integer',
            'business_id' => 'integer'
        ];
    }
}
