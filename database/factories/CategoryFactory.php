<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucfirst($name),
            'business_id' => Business::inRandomOrder()->first()->id ?? Business::factory(),
            'created_by' => User::inRandomOrder()->first()->id ?? User::factory(),
            'short_code' => strtoupper(substr($name, 0, 3)),
            'parent_id' => null,
            'category_type' => 'product',
            'description' => fake()->boolean(40) ? fake()->sentence() : null,
            'slug' => str()->slug($name),
            'is_active' => true,
            'deleted_at' => null,
        ];
    }

    /**
     * Create a subcategory
     */
    public function subcategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Category::factory(),
        ]);
    }

    /**
     * Create an expense category
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_type' => 'expense',
        ]);
    }
}
