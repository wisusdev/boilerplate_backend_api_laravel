<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory, HasUuids;

    protected $primaryKey = "uuid";

    public function role_has_permissions()
    {
        return $this->hasMany(RoleHasPermission::class, 'permission_id');
    }

    public function model_has_permissions()
    {
        return $this->hasMany(ModelHasPermission::class, 'permission_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'guard_name' => 'string'
        ];
    }
}
