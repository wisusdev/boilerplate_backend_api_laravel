<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Business;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);
        $sku = fake()->regexify('[A-Z]{2}[0-9]{6}');

        return [
            'business_id' => Business::inRandomOrder()->first()->id ?? Business::factory(),
            'brand_id' => Brand::inRandomOrder()->first()->id ?? Brand::factory(),
            'category_id' => Category::inRandomOrder()->first()->id ?? Category::factory(),
            'created_by' => User::inRandomOrder()->first()->id ?? User::factory(),
            'sub_category_id' => null,
            'tax' => TaxRate::inRandomOrder()->first()->id ?? TaxRate::factory(),
            'unit_id' => Unit::inRandomOrder()->first()->id ?? Unit::factory(),
            'name' => ucfirst($name),
            'type' => 'single',
            'secondary_unit_id' => null,
            'sub_unit_ids' => null,
            'tax_type' => fake()->randomElement(['inclusive', 'exclusive']),
            'enable_stock' => true,
            'alert_quantity' => fake()->numberBetween(5, 50),
            'sku' => $sku,
            'barcode_type' => fake()->randomElement(['C128', 'EAN13', 'EAN8']),
            'expiry_period' => fake()->boolean(30) ? fake()->numberBetween(1, 24) : null,
            'expiry_period_type' => fake()->randomElement(['days', 'months']),
            'enable_sr_no' => fake()->boolean(20),
            'weight' => fake()->boolean(40) ? fake()->randomFloat(2, 0.1, 50) . ' kg' : null,
            'image' => null,
            'product_description' => fake()->boolean(60) ? fake()->paragraph() : null,
            'product_custom_field1' => null,
            'product_custom_field2' => null,
            'product_custom_field3' => null,
            'product_custom_field4' => null,
            'product_custom_field5' => null,
            'product_custom_field6' => null,
            'product_custom_field7' => null,
            'product_custom_field8' => null,
            'product_custom_field9' => null,
            'product_custom_field10' => null,
            'product_custom_field11' => null,
            'product_custom_field12' => null,
            'product_custom_field13' => null,
            'product_custom_field14' => null,
            'product_custom_field15' => null,
            'product_custom_field16' => null,
            'product_custom_field17' => null,
            'product_custom_field18' => null,
            'product_custom_field19' => null,
            'product_custom_field20' => null,
            'preparation_time_in_minutes' => fake()->boolean(30) ? fake()->numberBetween(5, 60) : null,
            'warranty_id' => null,
            'is_inactive' => false,
            'not_for_selling' => false,
        ];
    }

    /**
     * Create a variable product with variations
     */
    public function variable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'variable',
        ]);
    }

    /**
     * Create a combo product
     */
    public function combo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'combo',
        ]);
    }

    /**
     * Create a modifier product
     */
    public function modifier(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'modifier',
        ]);
    }

    /**
     * Create an inactive product
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_inactive' => true,
        ]);
    }

    /**
     * Create a product not for selling
     */
    public function notForSelling(): static
    {
        return $this->state(fn (array $attributes) => [
            'not_for_selling' => true,
        ]);
    }

    /**
     * Create a product with expiry tracking
     */
    public function withExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_period' => fake()->numberBetween(30, 365),
            'expiry_period_type' => 'days',
        ]);
    }

    /**
     * Create a product with serial number tracking
     */
    public function withSerialNumber(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_sr_no' => true,
        ]);
    }
}
