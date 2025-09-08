<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'business_id' => 'integer',
            'code' => 'string',
            'parent_id' => 'integer',
            'deleted_at' => 'timestamp'
        ];
    }
}
