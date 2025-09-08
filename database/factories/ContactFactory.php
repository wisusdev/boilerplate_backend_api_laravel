<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fake()->randomNumber(),
            'type' => fake()->text(191),
            'contact_type' => fake()->text(191),
            'supplier_business_name' => fake()->text(191),
            'name' => fake()->text(191),
            'prefix' => fake()->text(191),
            'first_name' => fake()->text(191),
            'middle_name' => fake()->text(191),
            'last_name' => fake()->text(191),
            'email' => fake()->text(191),
            'contact_id' => fake()->text(191),
            'contact_status' => fake()->text(191),
            'tax_number' => fake()->text(191),
            'city' => fake()->text(191),
            'state' => fake()->text(191),
            'country' => fake()->text(191),
            'address_line_1' => fake()->paragraph(),
            'address_line_2' => fake()->paragraph(),
            'zip_code' => fake()->text(191),
            'dob' => fake()->date(),
            'mobile' => fake()->text(191),
            'landline' => fake()->text(191),
            'alternate_number' => fake()->text(191),
            'pay_term_number' => fake()->randomNumber(),
            'pay_term_type' => fake()->randomElement(['days','months']),
            'credit_limit' => fake()->randomFloat(4, 0, 999999999999999999),
            'created_by' => fake()->randomNumber(),
            'balance' => fake()->randomFloat(4, 0, 999999999999999999),
            'total_rp' => fake()->randomNumber(),
            'total_rp_used' => fake()->randomNumber(),
            'total_rp_expired' => fake()->randomNumber(),
            'is_default' => fake()->boolean(),
            'customer_group_id' => fake()->randomNumber(),
            'shipping_address' => fake()->paragraph(),
            'position' => fake()->text(191),
            'shipping_custom_field_details' => fake()->paragraph(),
            'is_export' => fake()->boolean(),
            'export_custom_field_1' => fake()->text(191),
            'export_custom_field_2' => fake()->text(191),
            'export_custom_field_3' => fake()->text(191),
            'export_custom_field_4' => fake()->text(191),
            'export_custom_field_5' => fake()->text(191),
            'export_custom_field_6' => fake()->text(191),
            'custom_field1' => fake()->text(191),
            'custom_field2' => fake()->text(191),
            'custom_field3' => fake()->text(191),
            'custom_field4' => fake()->text(191),
            'custom_field5' => fake()->text(191),
            'custom_field6' => fake()->text(191),
            'custom_field7' => fake()->text(191),
            'custom_field8' => fake()->text(191),
            'custom_field9' => fake()->text(191),
            'custom_field10' => fake()->text(191),
            'deleted_at' => fake()->unixTime(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
