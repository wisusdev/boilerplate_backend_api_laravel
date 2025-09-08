<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'name' => 'string',
            'account_number' => 'string',
            'account_type_id' => 'integer',
            'created_by' => 'integer',
            'is_closed' => 'boolean',
            'deleted_at' => 'timestamp'
        ];
    }
}
