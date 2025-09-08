<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // role
            'roles:index',
            'roles:create',
            'roles:store',
            'roles:show',
            'roles:edit',
            'roles:update',
            'roles:delete',

            // permission
            'permissions:index',
            'permissions:by-role',

            // user
            'users:index',
            'users:create',
            'users:store',
            'users:show',
            'users:edit',
            'users:update',
            'users:delete',

            // currency
            'currencies:index',
            'currencies:create',
            'currencies:store',
            'currencies:show',
            'currencies:edit',
            'currencies:update',
            'currencies:delete',
            'currencies:statistics',
            'currencies:businesses',

            // business
            'businesses:index',
            'businesses:create',
            'businesses:store',
            'businesses:show',
            'businesses:edit',
            'businesses:update',
            'businesses:delete',
            'businesses:toggle-status',
            'businesses:statistics',

            // business location
            'business_locations:index',
            'business_locations:create',
            'business_locations:store',
            'business_locations:show',
            'business_locations:edit',
            'business_locations:update',
            'business_locations:delete',
            'business_locations:restore',

            // product
            'products:index',
            'products:create',
            'products:store',
            'products:show',
            'products:edit',
            'products:update',
            'products:delete',
            'products:toggle-stock',
            'products:stock',
            'products:duplicate',

            // category
            'categories:index',
            'categories:create',
            'categories:store',
            'categories:show',
            'categories:edit',
            'categories:update',
            'categories:delete',
            'categories:bulk-destroy',
            'categories:statistics',
            'categories:products',
            'categories:change-status',
            'categories:restore',

            // brand
            'brands:index',
            'brands:create',
            'brands:store',
            'brands:show',
            'brands:edit',
            'brands:update',
            'brands:delete',
            'brands:bulk-destroy',
            'brands:statistics',
            'brands:products',
            'brands:change-status',
            'brands:restore',

            // unit
            'units:index',
            'units:create',
            'units:store',
            'units:show',
            'units:edit',
            'units:update',
            'units:delete',
            'units:statistics',
            'units:products',
            'units:change-status',
            'units:restore',
            'units:bulk-destroy',

            // variation
            'variations:index',
            'variations:create',
            'variations:store',
            'variations:show',
            'variations:edit',
            'variations:update',
            'variations:delete',
            'variations:statistics',
            'variations:stock',
            'variations:change-status',
            'variations:price',
            'variations:restore',
            'variations:bulk-update',
            'variations:bulk-destroy',

            // invoice scheme
            'invoice-schemes:index',
            'invoice-schemes:create',
            'invoice-schemes:store',
            'invoice-schemes:show',
            'invoice-schemes:edit',
            'invoice-schemes:update',
            'invoice-schemes:delete',
            'invoice-schemes:bulk-destroy',
            'invoice-schemes:statistics',
            'invoice-schemes:change-status',

            // invoice layout
            'invoice-layouts:index',
            'invoice-layouts:create',
            'invoice-layouts:store',
            'invoice-layouts:show',
            'invoice-layouts:edit',
            'invoice-layouts:update',
            'invoice-layouts:delete',
            'invoice-layouts:bulk-destroy',
            'invoice-layouts:statistics',
            'invoice-layouts:change-status',
            'invoice-layouts:duplicate',

            // printer
            'printers:index',
            'printers:create',
            'printers:store',
            'printers:show',
            'printers:edit',
            'printers:update',
            'printers:delete',

            // tax rate
            'tax-rates:index',
            'tax-rates:create',
            'tax-rates:store',
            'tax-rates:show',
            'tax-rates:edit',
            'tax-rates:update',
            'tax-rates:delete',
            'tax-rates:bulk',
            'tax-rates:statistics',
            'tax-rates:status',
            'tax-rates:restore',

            // Reference Count
            'reference_counts:index',
            'reference_counts:create',
            'reference_counts:store',
            'reference_counts:show',
            'reference_counts:edit',
            'reference_counts:update',
            'reference_counts:delete',

        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
