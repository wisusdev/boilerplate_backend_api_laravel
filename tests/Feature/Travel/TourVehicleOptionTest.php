<?php

namespace Tests\Feature\Travel;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TourVehicleOptionTest extends TestCase
{
    use RefreshDatabase;

    private function apiJson(string $method, string $uri, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function user(): User
    {
        return User::create([
            'username'   => 'veh' . uniqid(),
            'first_name' => 'Veh',
            'last_name'  => 'Option',
            'email'      => uniqid() . '@example.com',
            'password'   => bcrypt('password123'),
        ]);
    }

    private function tour(array $overrides = []): Tour
    {
        return Tour::create(array_merge([
            'title' => 'Vehicle Option Tour', 'description' => 'x', 'price' => 100,
            'max_capacity' => 20, 'location' => 'SV', 'currency_code' => 'USD', 'is_active' => true,
            'vehicle_options' => [
                ['name' => 'Sedán privado', 'surcharge' => 20],
                ['name' => 'Van con A/C', 'surcharge' => 35],
                ['name' => 'Microbús', 'surcharge' => 50],
            ],
        ], $overrides));
    }

    public function test_tour_resource_exposes_vehicle_options_and_sections(): void
    {
        $tour = $this->tour(['booking_sections' => ['coupon' => false]]);

        $response = $this->apiJson('GET', '/api/v1/tours/' . $tour->slug);

        $response->assertOk();
        $response->assertJsonPath('data.attributes.vehicle_options.2.name', 'Microbús');
        // Sección desactivada explícitamente...
        $response->assertJsonPath('data.attributes.booking_sections.coupon', false);
        // ...y el resto por defecto visibles.
        $response->assertJsonPath('data.attributes.booking_sections.vehicle', true);
        $response->assertJsonPath('data.attributes.booking_sections.pickup', true);
    }

    public function test_booking_with_vehicle_option_adds_surcharge(): void
    {
        Passport::actingAs($this->user());
        $tour = $this->tour();

        $response = $this->apiJson('POST', '/api/v1/bookings', [
            'data' => [
                'type' => 'bookings',
                'attributes' => [
                    'booking_type' => 'tour',
                    'tour_id' => $tour->id,
                    'booking_date' => now()->addDays(3)->toDateString(),
                    'pax_count' => 2,
                    'upgrade_option_index' => 2, // Microbús (+50)
                ],
            ],
        ]);

        $response->assertSuccessful();
        // 100 * 2 + 50 = 250
        $response->assertJsonPath('data.attributes.total_price', '250.00');
        $response->assertJsonPath('data.attributes.upgrade_label', 'Microbús');
        $this->assertDatabaseHas('bookings', [
            'bookable_id'   => $tour->id,
            'upgrade_label' => 'Microbús',
            'total_price'   => 250,
        ]);
    }

    public function test_booking_rejects_invalid_vehicle_option_index(): void
    {
        Passport::actingAs($this->user());
        $tour = $this->tour();

        $this->apiJson('POST', '/api/v1/bookings', [
            'data' => [
                'type' => 'bookings',
                'attributes' => [
                    'booking_type' => 'tour',
                    'tour_id' => $tour->id,
                    'booking_date' => now()->addDays(3)->toDateString(),
                    'pax_count' => 2,
                    'upgrade_option_index' => 9,
                ],
            ],
        ])->assertJsonApiValidationErrors('data.attributes.upgrade_option_index');
    }

    public function test_admin_can_configure_vehicle_options_and_sections(): void
    {
        \App\Models\Role::findOrCreate('admin', 'api');
        \App\Models\Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true]);
        $admin = $this->user();
        $admin->assignRole('admin');
        Passport::actingAs($admin);

        $response = $this->apiJson('POST', '/api/v1/tours', [
            'data' => [
                'type' => 'tours',
                'attributes' => [
                    'title' => 'Config Tour',
                    'description' => 'x',
                    'price' => 60,
                    'max_capacity' => 10,
                    'location' => 'SV',
                    'currency_code' => 'USD',
                    'vehicle_options' => [
                        ['name' => 'Sedán', 'surcharge' => 15],
                        ['name' => 'Van', 'surcharge' => 30],
                    ],
                    'booking_sections' => ['pickup' => false, 'coupon' => true],
                ],
            ],
        ]);

        $response->assertCreated();
        $tour = Tour::where('title', 'Config Tour')->firstOrFail();
        $this->assertCount(2, $tour->vehicleOptionsList());
        $this->assertFalse($tour->sectionVisible('pickup'));
        $this->assertTrue($tour->sectionVisible('coupon'));
    }
}
