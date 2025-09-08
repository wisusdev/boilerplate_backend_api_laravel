<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TransactionSellLinesPurchaseLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\TransactionSellLinesPurchaseLine::factory()->count(10)->create();
    }
}
