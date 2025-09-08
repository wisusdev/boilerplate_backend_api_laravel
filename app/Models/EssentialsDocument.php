<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsDocument extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'user_id' => 'integer',
            'type' => 'string',
            'name' => 'string',
            'description' => 'string'
        ];
    }
}
