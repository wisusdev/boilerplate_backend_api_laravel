<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GroupSubTaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\GroupSubTax::factory()->count(10)->create();
    }
}
