<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleHasPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\RoleHasPermission::factory()->count(10)->create();
    }
}
