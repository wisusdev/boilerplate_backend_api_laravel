<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessLocation;
use App\Models\InvoiceLayout;
use App\Models\InvoiceScheme;
use App\Models\Printer;
use App\Models\ReferenceCount;
use App\Utils\GeneralUtils;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessLocation>
 */
class BusinessLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business_id = Business::inRandomOrder()->first()->id ?? Business::factory();
        $referenceCount = ReferenceCount::where('business_id', $business_id)->first()->ref_count ?? 0;
        $locationId = app(GeneralUtils::class)->generateReferenceNumber('business_location', $referenceCount, $business_id, 'LOC');

        return [
            'name' => fake()->text(256),
            'business_id' => $business_id,
            'location_id' => $locationId,
            'invoice_layout_id' => InvoiceLayout::inRandomOrder()->first()->id ?? InvoiceLayout::factory()->create(['business_id' => $business_id])->id,
            'invoice_scheme_id' => InvoiceScheme::inRandomOrder()->first()->id ?? InvoiceScheme::factory()->create(['business_id' => $business_id])->id,
            'printer_id' => Printer::inRandomOrder()->first()->id ?? Printer::factory()->create(['business_id' => $business_id])->id,
            'landmark' => fake()->paragraph(),
            'country' => fake()->text(100),
            'state' => fake()->text(100),
            'city' => fake()->text(100),
            'zip_code' => fake()->randomLetter(),
            'sale_invoice_scheme_id' => fake()->randomNumber(),
            'sale_invoice_layout_id' => fake()->randomNumber(),
            'selling_price_group_id' => fake()->randomNumber(),
            'print_receipt_on_invoice' => fake()->boolean(),
            'receipt_printer_type' => fake()->randomElement(['browser','printer']),
            'mobile' => fake()->text(191),
            'alternate_number' => fake()->text(191),
            'email' => fake()->text(191),
            'website' => fake()->text(191),
            'featured_products' => fake()->paragraph(),
            'is_active' => fake()->boolean(),
            'default_payment_accounts' => fake()->paragraph(),
            'custom_field1' => fake()->text(191),
            'custom_field2' => fake()->text(191),
            'custom_field3' => fake()->text(191),
            'custom_field4' => fake()->text(191),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
            'deleted_at' => null
        ];
    }
}
