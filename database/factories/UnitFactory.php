<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    private static array $units = [
        ['name' => 'Pieza', 'short_name' => 'pz', 'allow_decimal' => false],
        ['name' => 'Kilogramo', 'short_name' => 'kg', 'allow_decimal' => true],
        ['name' => 'Gramo', 'short_name' => 'g', 'allow_decimal' => true],
        ['name' => 'Litro', 'short_name' => 'L', 'allow_decimal' => true],
        ['name' => 'Mililitro', 'short_name' => 'ml', 'allow_decimal' => true],
        ['name' => 'Metro', 'short_name' => 'm', 'allow_decimal' => true],
        ['name' => 'Centímetro', 'short_name' => 'cm', 'allow_decimal' => true],
        ['name' => 'Caja', 'short_name' => 'cj', 'allow_decimal' => false],
        ['name' => 'Paquete', 'short_name' => 'paq', 'allow_decimal' => false],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = fake()->randomElement(self::$units);

        return [
            'business_id' => Business::inRandomOrder()->first()->id ?? Business::factory(),
            'name' => $unit['name'],
            'short_name' => $unit['short_name'],
            'allow_decimal' => $unit['allow_decimal'],
            'base_unit_id' => null,
            'base_unit_multiplier' => 1.0,
            'created_by' => User::factory(),
            'deleted_at' => null,
        ];
    }

    /**
     * Create a piece unit
     */
    public function piece(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pieza',
            'short_name' => 'pz',
            'allow_decimal' => false,
        ]);
    }

    /**
     * Create a weight unit
     */
    public function kilogram(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Kilogramo',
            'short_name' => 'kg',
            'allow_decimal' => true,
        ]);
    }

    /**
     * Create a volume unit
     */
    public function liter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Litro',
            'short_name' => 'L',
            'allow_decimal' => true,
        ]);
    }
}
