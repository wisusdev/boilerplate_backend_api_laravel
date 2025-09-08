<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsDocument::factory()->count(10)->create();
    }
}
