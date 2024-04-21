<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'user',
            'admin',
        ];

        foreach ($roles as $role) {
            $newRole = Role::create(['name' => $role, 'guard_name' => 'api']);
            if ($newRole->name === 'admin') {
                $newRole->givePermissionTo(Permission::all());
            }
        }
    }
}
