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
			'settings:email',
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

            // package
            'packages:index',
			'packages:create',
            'packages:store',
            'packages:show',
			'packages:edit',
            'packages:update',
            'packages:delete',

            // subscription
            'subscriptions:index',
			'subscriptions:create',
            'subscriptions:store',
            'subscriptions:show',
			'subscriptions:edit',
            'subscriptions:update',
            'subscriptions:delete',

			// invoice
			'invoices:index',
			'invoices:create',
			'invoices:store',
			'invoices:show',
			'invoices:edit',
			'invoices:update',
			'invoices:delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
