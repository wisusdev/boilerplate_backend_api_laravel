<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAndNote extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'notable_id' => 'integer',
            'notable_type' => 'string',
            'is_private' => 'boolean',
            'created_by' => 'integer'
        ];
    }
}
