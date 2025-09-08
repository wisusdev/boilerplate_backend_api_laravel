<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TransactionSellLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\TransactionSellLine::factory()->count(10)->create();
    }
}
