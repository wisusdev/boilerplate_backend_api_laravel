<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategorizableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Categorizable::factory()->count(10)->create();
    }
}
