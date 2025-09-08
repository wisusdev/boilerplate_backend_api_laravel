<?php

namespace Database\Seeders;

use App\Models\InvoiceScheme;
use Illuminate\Database\Seeder;

class InvoiceSchemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        InvoiceScheme::factory()->count(10)->create();
    }
}
