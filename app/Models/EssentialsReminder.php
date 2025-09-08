<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsReminder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'user_id' => 'integer',
            'name' => 'string',
            'date' => 'date'
        ];
    }
}
