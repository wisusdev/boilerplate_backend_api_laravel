<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperadminFrontendPage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'title' => 'string',
            'slug' => 'string',
            'is_shown' => 'boolean',
            'menu_order' => 'integer'
        ];
    }
}
