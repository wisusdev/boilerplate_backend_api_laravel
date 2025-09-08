<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsTodoCommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsTodoComment::factory()->count(10)->create();
    }
}
