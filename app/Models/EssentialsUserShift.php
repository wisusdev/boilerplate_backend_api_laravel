<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsUserShift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'essentials_shift_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date'
        ];
    }
}
