<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medium extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'file_name' => 'string',
            'uploaded_by' => 'integer',
            'model_type' => 'string',
            'model_media_type' => 'string'
        ];
    }
}
