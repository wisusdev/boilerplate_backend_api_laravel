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
            // setting
            'settings:index',
            'settings:show',
            'settings:update',

            // role
            'roles:index',
            'roles:store',
            'roles:show',
            'roles:update',
            'roles:delete',

            // user
            'users:index',
            'users:store',
            'users:show',
            'users:update',
            'users:delete',

            // package
            'packages:index',
            'packages:store',
            'packages:show',
            'packages:update',
            'packages:delete',

            // subscription
            'subscriptions:index',
            'subscriptions:store',
            'subscriptions:show',
            'subscriptions:update',
            'subscriptions:delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
