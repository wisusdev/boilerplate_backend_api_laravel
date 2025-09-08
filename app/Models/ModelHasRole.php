<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelHasRole extends Model
{
    use HasFactory;

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    protected $primaryKey = 'role_id';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'model_type' => 'string'
        ];
    }
}
