<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VariationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\VariationTemplate::factory()->count(10)->create();
    }
}
