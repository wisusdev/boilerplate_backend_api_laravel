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
            'settings:app',
			'settings:taxes',
			'settings:shipping',
			'settings:social_auth_services',
			'settings:mail',
			'settings:push_notification',
			'settings:payment_gateway',

            // role
            'roles:index',
			'roles:create',
            'roles:store',
            'roles:show',
			'roles:edit',
            'roles:update',
            'roles:delete',

            // user
            'users:index',
			'users:create',
            'users:store',
            'users:show',
			'users:edit',
            'users:update',
            'users:delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
