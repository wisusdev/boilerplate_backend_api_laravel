<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;


class Role extends SpatieRole
{
    use HasFactory;

	public function users(): BelongsToMany
	{
		return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id');
	}

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function role_has_permissions(): HasMany|Role
    {
        return $this->hasMany(RoleHasPermission::class, 'role_id');
    }

    public function model_has_roles(): HasMany|Role
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
