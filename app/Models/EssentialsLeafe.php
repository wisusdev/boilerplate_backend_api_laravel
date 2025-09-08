<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsLeafe extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'essentials_leave_type_id' => 'integer',
            'business_id' => 'integer',
            'user_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'ref_no' => 'string'
        ];
    }
}
