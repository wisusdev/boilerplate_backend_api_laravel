<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsPayrollGroupTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsPayrollGroupTransaction::factory()->count(10)->create();
    }
}
