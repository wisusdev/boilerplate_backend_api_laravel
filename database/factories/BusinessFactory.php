<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency_id' => Currency::inRandomOrder()->first()?->id ?? Currency::factory(),
            'owner_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'start_date' => fake()->date(),
            'tax_number_1' => fake()->regexify('[A-Z0-9]{10}'),
            'tax_label_1' => 'RFC',
            'tax_number_2' => fake()->regexify('[A-Z0-9]{8}'),
            'tax_label_2' => 'CURP',
            'code_label_1' => 'Código Interno',
            'code_1' => fake()->regexify('BUS[0-9]{6}'),
            'code_label_2' => 'Código Secundario',
            'code_2' => fake()->regexify('SEC[0-9]{6}'),
            'default_sales_tax' => null,
            'default_profit_percent' => fake()->randomFloat(2, 10, 50),
            'time_zone' => fake()->timezone(),
            'fy_start_month' => fake()->numberBetween(1, 12),
            'accounting_method' => fake()->randomElement(['fifo', 'lifo', 'avco']),
            'default_sales_discount' => fake()->randomFloat(2, 0, 15),
            'sell_price_tax' => fake()->randomElement(['includes', 'excludes']),
            'logo' => null,
            'sku_prefix' => fake()->regexify('[A-Z]{2,4}'),
            'enable_product_expiry' => fake()->boolean(),
            'expiry_type' => fake()->randomElement(['add_expiry', 'add_manufacturing']),
            'on_product_expiry' => fake()->randomElement(['keep_selling', 'stop_selling', 'auto_delete']),
            'stop_selling_before' => fake()->numberBetween(1, 30),
            'enable_tooltip' => fake()->boolean(),
            'purchase_in_diff_currency' => fake()->boolean(),
            'purchase_currency_id' => null,
            'p_exchange_rate' => fake()->randomFloat(4, 0.1, 100),
            'transaction_edit_days' => fake()->numberBetween(1, 30),
            'stock_expiry_alert_days' => fake()->numberBetween(7, 60),
            'keyboard_shortcuts' => json_encode([]),
            'pos_settings' => json_encode([]),
            'weighing_scale_setting' => json_encode([]),
            'essentials_settings' => json_encode([]),
            'enable_brand' => true,
            'enable_category' => true,
            'enable_sub_category' => fake()->boolean(),
            'enable_price_tax' => true,
            'enable_purchase_status' => true,
            'enable_lot_number' => fake()->boolean(),
            'default_unit' => null,
            'enable_sub_units' => fake()->boolean(),
            'enable_racks' => fake()->boolean(),
            'enable_row' => fake()->boolean(),
            'enable_position' => fake()->boolean(),
            'enable_editing_product_from_purchase' => true,
            'sales_cmsn_agnt' => fake()->randomElement(['logged_in_user', 'user', 'cmsn_agnt']),
            'item_addition_method' => fake()->boolean(),
            'enable_inline_tax' => true,
            'currency_symbol_placement' => fake()->randomElement(['before', 'after']),
            'enabled_modules' => json_encode(['purchases', 'sales', 'inventory']),
            'date_format' => fake()->randomElement(['d/m/Y', 'm/d/Y', 'Y-m-d']),
            'time_format' => fake()->randomElement(['12', '24']),
            'currency_precision' => 2,
            'quantity_precision' => 2,
            'ref_no_prefixes' => json_encode([
                'purchase' => 'PUR',
                'sale' => 'VEN',
                'stock_adjustment' => 'SA'
            ]),
            'theme_color' => fake()->hexColor(),
            'created_by' => User::factory(),
            'enable_rp' => fake()->boolean(),
            'rp_name' => 'Puntos de Lealtad',
            'amount_for_unit_rp' => fake()->randomFloat(2, 100, 1000),
            'min_order_total_for_rp' => fake()->randomFloat(2, 500, 2000),
            'max_rp_per_order' => fake()->numberBetween(100, 1000),
            'redeem_amount_per_unit_rp' => fake()->randomFloat(2, 1, 10),
            'min_order_total_for_redeem' => fake()->randomFloat(2, 100, 500),
            'min_redeem_point' => fake()->numberBetween(10, 50),
            'max_redeem_point' => fake()->numberBetween(500, 1000),
            'rp_expiry_period' => fake()->numberBetween(6, 24),
            'rp_expiry_type' => fake()->randomElement(['month', 'year']),
            'email_settings' => json_encode([]),
            'sms_settings' => json_encode([]),
            'custom_labels' => json_encode([]),
            'common_settings' => json_encode([]),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the business is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the business uses different currency for purchases.
     */
    public function withPurchaseCurrency(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_in_diff_currency' => true,
            'purchase_currency_id' => Currency::inRandomOrder()->first()?->id ?? Currency::factory(),
            'p_exchange_rate' => fake()->randomFloat(4, 0.1, 100),
        ]);
    }
}
