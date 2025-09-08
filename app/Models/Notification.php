<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => 'string',
            'notifiable_type' => 'string',
            'read_at' => 'timestamp'
        ];
    }
}
