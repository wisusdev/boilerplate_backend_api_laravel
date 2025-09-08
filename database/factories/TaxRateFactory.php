<?php

namespace Database\Factories;

use App\Models\TaxRate;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::inRandomOrder()->first()->id ?? Business::factory(),
            'created_by' => User::inRandomOrder()->first()->id ?? User::factory(),
            'name' => fake()->randomElement(['IVA 16%', 'IVA 8%', 'IEPS 25%', 'Exento']),
            'amount' => fake()->randomElement([0, 8, 16, 25]),
            'is_tax_group' => false,
            'for_tax_group' => false,
            'deleted_at' => null,
        ];
    }

    /**
     * Create a standard VAT tax
     */
    public function vat16(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'IVA 16%',
            'amount' => 16.00,
        ]);
    }

    /**
     * Create reduced VAT tax
     */
    public function vat8(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'IVA 8%',
            'amount' => 8.00,
        ]);
    }

    /**
     * Create exempt tax
     */
    public function exempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Exento',
            'amount' => 0.00,
        ]);
    }

    /**
     * Create a tax group
     */
    public function taxGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_tax_group' => true,
            'for_tax_group' => false,
        ]);
    }
}
