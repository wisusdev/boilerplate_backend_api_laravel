<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsTodosUser extends Model
{
    use HasFactory;

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'todo_id' => 'integer',
            'user_id' => 'integer'
        ];
    }
}
