<?php

namespace Tests\Feature\Travel;

use App\Models\Tour;
use App\Models\TransportVehicle;
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

    private function vehicle(string $title): TransportVehicle
    {
        return TransportVehicle::create([
            'title' => $title, 'vehicle_type' => 'van', 'location' => 'SV',
            'capacity' => 12, 'currency_code' => 'USD', 'is_active' => true,
            'daily_rate' => 100,
        ]);
    }

    private function tour(array $overrides = []): Tour
    {
        // Opciones referencian vehículos reales del catálogo; el precio (surcharge) lo fija el admin.
        $v1 = $this->vehicle('Sedán privado');
        $v2 = $this->vehicle('Van con A/C');
        $v3 = $this->vehicle('Microbús');

        return Tour::create(array_merge([
            'title' => 'Vehicle Option Tour', 'description' => 'x', 'price' => 100,
            'max_capacity' => 20, 'location' => 'SV', 'currency_code' => 'USD', 'is_active' => true,
            'vehicle_options' => [
                ['vehicle_id' => $v1->id, 'name' => 'Sedán privado', 'surcharge' => 20],
                ['vehicle_id' => $v2->id, 'name' => 'Van con A/C', 'surcharge' => 35],
                ['vehicle_id' => $v3->id, 'name' => 'Microbús', 'surcharge' => 50],
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

        $microbusVehicleId = $tour->vehicleOptionsList()[2]['vehicle_id'];

        $response->assertSuccessful();
        // 100 * 2 + 50 = 250 (precio fijado por el admin)
        $response->assertJsonPath('data.attributes.total_price', '250.00');
        $response->assertJsonPath('data.attributes.upgrade_label', 'Microbús');
        $response->assertJsonPath('data.attributes.upgrade_vehicle_id', $microbusVehicleId);
        $response->assertJsonPath('data.attributes.upgrade_vehicle_title', 'Microbús');
        $this->assertDatabaseHas('bookings', [
            'bookable_id'        => $tour->id,
            'upgrade_vehicle_id' => $microbusVehicleId,
            'upgrade_label'      => 'Microbús',
            'upgrade_surcharge'  => 50,
            'total_price'        => 250,
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

        $v1 = $this->vehicle('Sedán');
        $v2 = $this->vehicle('Van');

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
                        ['vehicle_id' => $v1->id, 'name' => 'Sedán', 'surcharge' => 15],
                        ['vehicle_id' => $v2->id, 'name' => 'Van', 'surcharge' => 30],
                    ],
                    'booking_sections' => ['pickup' => false, 'coupon' => true],
                ],
            ],
        ]);

        $response->assertCreated();
        // El vehicle_id se persiste y expone en el recurso.
        $response->assertJsonPath('data.attributes.vehicle_options.0.vehicle_id', $v1->id);
        $tour = Tour::where('title', 'Config Tour')->firstOrFail();
        $this->assertCount(2, $tour->vehicleOptionsList());
        $this->assertSame($v2->id, $tour->vehicleOptionsList()[1]['vehicle_id']);
        $this->assertFalse($tour->sectionVisible('pickup'));
        $this->assertTrue($tour->sectionVisible('coupon'));
    }
}
