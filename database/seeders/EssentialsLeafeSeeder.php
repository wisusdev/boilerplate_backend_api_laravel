<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsLeafeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsLeafe::factory()->count(10)->create();
    }
}
