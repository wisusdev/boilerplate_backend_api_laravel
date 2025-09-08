<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class, // Depende de PermissionSeeder
            UserSeeder::class, // Depende de PermissionSeeder, RoleSeeder

            // Business-related seeders
            ReferenceCountSeeder::class, // Depende de BusinessSeeder
            CurrencySeeder::class, // Depende de UserSeeder
            BusinessSeeder::class, // Depende de UserSeeder, CurrencySeeder
            InvoiceSchemeSeeder::class, // Depende de BusinessSeeder
            InvoiceLayoutSeeder::class, // Depende de BusinessSeeder
            PrinterSeeder::class, // Depende de BusinessSeeder
            BusinessLocationSeeder::class, // Depende de BusinessSeeder, InvoiceSchemeSeeder, InvoiceLayoutSeeder
            BrandSeeder::class, // Depende de BusinessSeeder y UserSeeder
            CategorySeeder::class, // Depende de BusinessSeeder y UserSeeder
            TaxRateSeeder::class, // Depende de BusinessSeeder y UserSeeder
            UnitSeeder::class, // Depende de BusinessSeeder
            ProductSeeder::class, // Depende de BusinessSeeder, BrandSeeder, CategorySeeder, TaxRateSeeder, UnitSeeder, UserSeeder
        ]);
    }
}
