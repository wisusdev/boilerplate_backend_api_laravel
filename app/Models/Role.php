<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;


class Role extends SpatieRole
{
    use HasFactory, HasUuids;

    protected $primaryKey = "uuid";

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function role_has_permissions()
    {
        return $this->hasMany(RoleHasPermission::class, 'role_id');
    }

    public function model_has_roles()
    {
        return $this->hasMany(ModelHasRole::class, 'role_id');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'guard_name' => 'string',
            'business_id' => 'integer',
            'is_default' => 'boolean',
            'is_service_staff' => 'boolean'
        ];
    }
}
