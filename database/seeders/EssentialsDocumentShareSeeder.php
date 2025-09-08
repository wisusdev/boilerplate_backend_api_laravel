<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsDocumentShareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsDocumentShare::factory()->count(10)->create();
    }
}
