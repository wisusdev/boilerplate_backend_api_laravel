<?php

namespace Tests\Unit\Services\Booking;

use App\Models\Booking;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Services\Booking\TransportBookingHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransportBookingHandlerTest extends TestCase
{
    use RefreshDatabase;

    private TransportBookingHandler $handler;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new TransportBookingHandler();
        $this->user = User::create([
            'username'   => 'transport_tester',
            'first_name' => 'Transport',
            'last_name'  => 'Tester',
            'email'      => 'transport@example.com',
            'password'   => bcrypt('password123'),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createVehicle(array $overrides = []): TransportVehicle
    {
        return TransportVehicle::create(array_merge([
            'title'        => 'Test Van',
            'vehicle_type' => 'van',
            'description'  => 'Air conditioned',
            'location'     => 'San Salvador',
            'hourly_rate'  => 20.0,
            'daily_rate'   => 100.0,
            'capacity'     => 10,
            'currency_code' => 'USD',
            'is_active'    => true,
        ], $overrides));
    }

    private function baseData(TransportVehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'transport_vehicle_id' => $vehicle->id,
            'pickup_at'            => '2026-08-01 08:00:00',
            'dropoff_at'           => '2026-08-01 12:00:00',
            'pickup_location'      => 'Aeropuerto',
            'dropoff_location'     => 'Hotel',
            'rental_type'          => 'hourly',
            'quantity'             => 1,
            'currency_code'        => 'USD',
        ], $overrides);
    }

    // ─── prepare() price calculations ─────────────────────────────────────────

    public function test_prepare_calculates_hourly_price_for_4_hours(): void
    {
        $vehicle = $this->createVehicle(['hourly_rate' => 25.0]);

        $result = $this->handler->prepare($this->baseData($vehicle, [
            'pickup_at'  => '2026-08-01 08:00:00',
            'dropoff_at' => '2026-08-01 12:00:00', // 4 hours
            'rental_type' => 'hourly',
        ]));

        // 4 hours × $25 = $100
        $this->assertEquals(100.0, $result['total_price']);
    }

    public function test_prepare_uses_global_currency_ignoring_vehicle(): void
    {
        // La moneda es GLOBAL del sitio; la del vehículo (columna dormida) se ignora.
        $vehicle = $this->createVehicle(['currency_code' => 'EUR', 'daily_rate' => 100.0]);

        $result = $this->handler->prepare($this->baseData($vehicle, [
            'pickup_at'  => '2026-08-01 08:00:00',
            'dropoff_at' => '2026-08-02 08:00:00',
            'rental_type' => 'daily',
        ]));

        $this->assertSame('USD', $result['currency_code']);
    }

    public function test_prepare_uses_minimum_1_hour_for_short_rentals(): void
    {
        $vehicle = $this->createVehicle(['hourly_rate' => 30.0]);

        $result = $this->handler->prepare($this->baseData($vehicle, [
            'pickup_at'  => '2026-08-01 10:00:00',
            'dropoff_at' => '2026-08-01 10:30:00', // 30 min → capped to 1h
            'rental_type' => 'hourly',
        ]));

        $this->assertEquals(30.0, $result['total_price']);
    }

    public function test_prepare_calculates_daily_price_for_1_day(): void
    {
        $vehicle = $this->createVehicle(['daily_rate' => 120.0]);

        $result = $this->handler->prepare($this->baseData($vehicle, [
            'pickup_at'  => '2026-08-01 08:00:00',
            'dropoff_at' => '2026-08-02 08:00:00', // 1 day
            'rental_type' => 'daily',
        ]));

        $this->assertEquals(120.0, $result['total_price']);
    }

    public function test_prepare_multiplies_price_by_quantity(): void
    {
        $vehicle = $this->createVehicle(['hourly_rate' => 20.0]);

        $result = $this->handler->prepare($this->baseData($vehicle, [
            'pickup_at'  => '2026-08-01 08:00:00',
            'dropoff_at' => '2026-08-01 10:00:00', // 2 hours
            'rental_type' => 'hourly',
            'quantity'   => 3,
        ]));

        // 2 hours × $20 × 3 vehicles = $120
        $this->assertEquals(120.0, $result['total_price']);
    }

    public function test_prepare_returns_correct_structure(): void
    {
        $vehicle = $this->createVehicle();
        $data    = $this->baseData($vehicle);

        $result = $this->handler->prepare($data);

        $this->assertArrayHasKey('bookable_type', $result);
        $this->assertArrayHasKey('bookable_id', $result);
        $this->assertArrayHasKey('starts_at', $result);
        $this->assertArrayHasKey('ends_at', $result);
        $this->assertArrayHasKey('total_price', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertEquals(TransportVehicle::class, $result['bookable_type']);
        $this->assertEquals($vehicle->id, $result['bookable_id']);
        $this->assertEquals('Aeropuerto', $result['details']['pickup_location']);
    }

    // ─── validate() overlap detection ─────────────────────────────────────────

    public function test_validate_throws_when_booking_overlaps_existing_confirmed(): void
    {
        $vehicle = $this->createVehicle();

        Booking::create([
            'user_id'       => $this->user->id,
            'bookable_type' => TransportVehicle::class,
            'bookable_id'   => $vehicle->id,
            'starts_at'     => '2026-08-01 08:00:00',
            'ends_at'       => '2026-08-01 16:00:00',
            'party_size'    => 1,
            'total_price'   => 160.0,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_CONFIRMED,
        ]);

        $this->expectException(ValidationException::class);

        $this->handler->validate([
            'transport_vehicle_id' => $vehicle->id,
            'pickup_at'            => '2026-08-01 10:00:00',
            'dropoff_at'           => '2026-08-01 14:00:00',
        ]);
    }

    public function test_validate_throws_when_booking_overlaps_pending(): void
    {
        $vehicle = $this->createVehicle();

        Booking::create([
            'user_id'       => $this->user->id,
            'bookable_type' => TransportVehicle::class,
            'bookable_id'   => $vehicle->id,
            'starts_at'     => '2026-08-01 08:00:00',
            'ends_at'       => '2026-08-01 16:00:00',
            'party_size'    => 1,
            'total_price'   => 80.0,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_PENDING,
        ]);

        $this->expectException(ValidationException::class);

        $this->handler->validate([
            'transport_vehicle_id' => $vehicle->id,
            'pickup_at'            => '2026-08-01 12:00:00',
            'dropoff_at'           => '2026-08-01 18:00:00',
        ]);
    }

    public function test_validate_allows_booking_after_existing_one_ends(): void
    {
        $vehicle = $this->createVehicle();

        Booking::create([
            'user_id'       => $this->user->id,
            'bookable_type' => TransportVehicle::class,
            'bookable_id'   => $vehicle->id,
            'starts_at'     => '2026-08-01 08:00:00',
            'ends_at'       => '2026-08-01 12:00:00',
            'party_size'    => 1,
            'total_price'   => 80.0,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_CONFIRMED,
        ]);

        // No debe lanzar excepción
        $this->handler->validate([
            'transport_vehicle_id' => $vehicle->id,
            'pickup_at'            => '2026-08-01 13:00:00',
            'dropoff_at'           => '2026-08-01 17:00:00',
        ]);

        $this->assertTrue(true);
    }

    public function test_validate_ignores_cancelled_bookings_for_overlap(): void
    {
        $vehicle = $this->createVehicle();

        Booking::create([
            'user_id'       => $this->user->id,
            'bookable_type' => TransportVehicle::class,
            'bookable_id'   => $vehicle->id,
            'starts_at'     => '2026-08-01 08:00:00',
            'ends_at'       => '2026-08-01 16:00:00',
            'party_size'    => 1,
            'total_price'   => 80.0,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_CANCELLED,
        ]);

        // No debe lanzar excepción porque el booking existente está cancelado
        $this->handler->validate([
            'transport_vehicle_id' => $vehicle->id,
            'pickup_at'            => '2026-08-01 10:00:00',
            'dropoff_at'           => '2026-08-01 14:00:00',
        ]);

        $this->assertTrue(true);
    }
}
