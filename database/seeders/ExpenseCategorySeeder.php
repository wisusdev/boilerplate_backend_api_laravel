<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ExpenseCategory::factory()->count(10)->create();
    }
}
