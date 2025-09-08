<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsTodoComment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'comment_by' => 'integer'
        ];
    }
}
