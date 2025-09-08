<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'country' => 'México',
                'country_code' => 'MX',
                'currency' => 'Peso mexicano',
                'code' => 'MXN',
                'symbol' => '$',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'country' => 'Estados Unidos',
                'country_code' => 'US',
                'currency' => 'Dólar estadounidense',
                'code' => 'USD',
                'symbol' => '$',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'country' => 'España',
                'country_code' => 'ES',
                'currency' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
            [
                'country' => 'Reino Unido',
                'country_code' => 'GB',
                'currency' => 'Libra esterlina',
                'code' => 'GBP',
                'symbol' => '£',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            [
                'country' => 'Japón',
                'country_code' => 'JP',
                'currency' => 'Yen japonés',
                'code' => 'JPY',
                'symbol' => '¥',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                $currency
            );
        }
    }
}
