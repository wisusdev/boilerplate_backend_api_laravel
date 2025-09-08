<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsHoliday extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'start_date' => 'date',
            'end_date' => 'date',
            'business_id' => 'integer',
            'location_id' => 'integer'
        ];
    }
}
