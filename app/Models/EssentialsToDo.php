<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsToDo extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'date' => 'datetime',
            'end_date' => 'datetime',
            'task_id' => 'string',
            'status' => 'string',
            'estimated_hours' => 'string',
            'priority' => 'string',
            'created_by' => 'integer'
        ];
    }
}
