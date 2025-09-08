<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

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
            'name' => fake()->company(),
            'description' => fake()->boolean(40) ? fake()->sentence() : null,
            'slug' => fake()->slug(),
            'deleted_at' => null,
        ];
    }
}
