<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    private static $currencies = [
        ['country' => 'Estados Unidos', 'country_code' => 'US', 'currency' => 'Dólar estadounidense', 'code' => 'USD', 'symbol' => '$'],
        ['country' => 'México', 'country_code' => 'MX', 'currency' => 'Peso mexicano', 'code' => 'MXN', 'symbol' => '$'],
        ['country' => 'España', 'country_code' => 'ES', 'currency' => 'Euro', 'code' => 'EUR', 'symbol' => '€'],
        ['country' => 'Reino Unido', 'country_code' => 'GB', 'currency' => 'Libra esterlina', 'code' => 'GBP', 'symbol' => '£'],
        ['country' => 'Japón', 'country_code' => 'JP', 'currency' => 'Yen japonés', 'code' => 'JPY', 'symbol' => '¥'],
        ['country' => 'Canadá', 'country_code' => 'CA', 'currency' => 'Dólar canadiense', 'code' => 'CAD', 'symbol' => '$'],
        ['country' => 'Australia', 'country_code' => 'AU', 'currency' => 'Dólar australiano', 'code' => 'AUD', 'symbol' => '$'],
        ['country' => 'Suiza', 'country_code' => 'CH', 'currency' => 'Franco suizo', 'code' => 'CHF', 'symbol' => 'Fr'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currency = fake()->randomElement(self::$currencies);
        
        return [
            'country' => $currency['country'],
            'country_code' => $currency['country_code'],
            'currency' => $currency['currency'],
            'code' => $currency['code'],
            'symbol' => $currency['symbol'],
            'thousand_separator' => ',',
            'decimal_separator' => '.',
        ];
    }

    /**
     * Create USD currency
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'Estados Unidos',
            'country_code' => 'US',
            'currency' => 'Dólar estadounidense',
            'code' => 'USD',
            'symbol' => '$',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
        ]);
    }

    /**
     * Create MXN currency
     */
    public function mxn(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'México',
            'country_code' => 'MX',
            'currency' => 'Peso mexicano',
            'code' => 'MXN',
            'symbol' => '$',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
        ]);
    }

    /**
     * Create EUR currency
     */
    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'España',
            'country_code' => 'ES',
            'currency' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);
    }
}
