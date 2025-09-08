<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ModelHasPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ModelHasPermission::factory()->count(10)->create();
    }
}
