<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DocumentAndNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\DocumentAndNote::factory()->count(10)->create();
    }
}
