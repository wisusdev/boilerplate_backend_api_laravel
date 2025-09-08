<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ModelHasRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ModelHasRole::factory()->count(10)->create();
    }
}
