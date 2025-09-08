<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VariationValueTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\VariationValueTemplate::factory()->count(10)->create();
    }
}
