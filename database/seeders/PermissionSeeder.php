<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // env file
            'env:index',
            'env:update',

            // setting
            'setting:index',
            'setting:update',

            // role
            'role:index',
            'role:create',
            'role:update',
            'role:delete',

            // user
            'user:index',
            'user:show',
            'user:create',
            'user:update',
            'user:delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
