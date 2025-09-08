<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleHasPermission extends Model
{
    use HasFactory;

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    protected $primaryKey = 'permission_id';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'permission_id' => 'integer',
            'role_id' => 'integer'
        ];
    }
}
