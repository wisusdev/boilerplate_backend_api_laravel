<?php

namespace Tests\Unit\Services\Booking;

use App\Models\Tour;
use App\Services\Booking\TourBookingHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TourBookingHandlerTest extends TestCase
{
    use RefreshDatabase;

    private TourBookingHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(TourBookingHandler::class);
    }

    private function createTour(array $overrides = []): Tour
    {
        return Tour::create(array_merge([
            'title' => 'Test Tour', 'description' => 'x', 'price' => 100,
            'max_capacity' => 20, 'location' => 'SV', 'currency_code' => 'USD',
        ], $overrides));
    }

    private function bookingData(Tour $tour, array $extra = []): array
    {
        return array_merge([
            'tour_id' => $tour->id,
            'pax_count' => 2,
            'booking_date' => now()->addDays(5)->toDateString(),
        ], $extra);
    }

    public function test_base_price_is_multiplied_by_pax(): void
    {
        $tour = $this->createTour(['price' => 100]);
        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 3]));

        $this->assertSame(300.0, (float) $prepared['total_price']);
        $this->assertNull($prepared['service_fees']);
    }

    public function test_sale_price_is_charged_when_lower(): void
    {
        $tour = $this->createTour(['price' => 100, 'sale_price' => 80]);
        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 2]));

        $this->assertSame(160.0, (float) $prepared['total_price']);
    }

    public function test_service_fees_are_added_and_snapshotted(): void
    {
        $tour = $this->createTour([
            'price' => 100,
            'service_fees' => [
                ['name' => 'Seguro', 'amount' => 10, 'calc' => 'fixed'],
                ['name' => 'Equipo', 'amount' => 5, 'calc' => 'per_person'],
            ],
        ]);

        // 100*2 + 10 (fijo) + 5*2 (por persona) = 220
        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 2, 'service_fees' => [0, 1]]));

        $this->assertSame(220.0, (float) $prepared['total_price']);
        $this->assertCount(2, $prepared['service_fees']);
        $this->assertSame(10.0, (float) $prepared['service_fees'][1]['total']); // 5 * 2 pax
    }

    public function test_invalid_service_fee_index_is_ignored(): void
    {
        $tour = $this->createTour([
            'price' => 100,
            'service_fees' => [['name' => 'Seguro', 'amount' => 10, 'calc' => 'fixed']],
        ]);

        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 1, 'service_fees' => [99, 0]]));

        $this->assertSame(110.0, (float) $prepared['total_price']);
    }

    public function test_vehicle_option_surcharge_is_added_to_total(): void
    {
        $tour = $this->createTour([
            'price' => 100,
            'vehicle_options' => [
                ['vehicle_id' => 3, 'name' => 'Sedán', 'surcharge' => 20],
                ['vehicle_id' => 7, 'name' => 'Van A/C', 'surcharge' => 50],
            ],
        ]);

        // 100 * 2 pax + 50 (opción índice 1) = 250
        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 2, 'upgrade_option_index' => 1]));

        $this->assertSame(250.0, (float) $prepared['total_price']);
        $this->assertSame(7, $prepared['upgrade_vehicle_id']);   // referencia al vehículo
        $this->assertSame('Van A/C', $prepared['upgrade_label']);
        $this->assertSame(50.0, (float) $prepared['upgrade_surcharge']); // precio del admin
    }

    public function test_vehicle_option_is_optional(): void
    {
        $tour = $this->createTour(['price' => 100, 'vehicle_options' => [['name' => 'Sedán', 'surcharge' => 20]]]);

        // Sin índice: no se cobra opción y no hay etiqueta.
        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 2]));

        $this->assertSame(200.0, (float) $prepared['total_price']);
        $this->assertNull($prepared['upgrade_label']);
        $this->assertNull($prepared['upgrade_surcharge']);
    }

    public function test_invalid_vehicle_option_index_is_rejected(): void
    {
        $tour = $this->createTour(['vehicle_options' => [['name' => 'Sedán', 'surcharge' => 20]]]);

        $this->expectException(ValidationException::class);
        // Índice 5 no existe entre las opciones del tour.
        $this->handler->validate($this->bookingData($tour, ['pax_count' => 2, 'upgrade_option_index' => 5]));
    }

    public function test_pricing_tier_discount_applies_per_person(): void
    {
        // Base $85; tramo 2+ pax = 17.65% off ≈ $70/persona.
        $tour = $this->createTour([
            'price' => 85,
            'pricing_tiers' => [
                ['min_pax' => 2, 'discount_percent' => 17.65],
            ],
        ]);

        // 1 persona: sin tramo → 85.
        $one = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 1]));
        $this->assertSame(85.0, (float) $one['total_price']);

        // 2 personas: 70 c/u → 140.
        $two = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 2]));
        $this->assertSame(140.0, (float) $two['total_price']);
    }

    public function test_pricing_tier_picks_highest_matching_min_pax(): void
    {
        $tour = $this->createTour([
            'price' => 100,
            'pricing_tiers' => [
                ['min_pax' => 2, 'discount_percent' => 10],
                ['min_pax' => 5, 'discount_percent' => 20],
            ],
        ]);

        // 6 pax cae en el tramo de 5+ (20% off): 80 * 6 = 480.
        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 6]));
        $this->assertSame(480.0, (float) $prepared['total_price']);
    }

    public function test_pricing_tier_stacks_on_sale_price(): void
    {
        // Oferta 80 sobre base 100; tramo 2+ = 10% off → 72/persona.
        $tour = $this->createTour([
            'price' => 100,
            'sale_price' => 80,
            'pricing_tiers' => [['min_pax' => 2, 'discount_percent' => 10]],
        ]);

        $prepared = $this->handler->prepare($this->bookingData($tour, ['pax_count' => 2]));
        $this->assertSame(144.0, (float) $prepared['total_price']); // 72 * 2
    }

    public function test_pickup_point_is_stored(): void
    {
        $tour = $this->createTour();
        $prepared = $this->handler->prepare($this->bookingData($tour, [
            'pickup_address' => 'Hotel Real, San Salvador',
            'pickup_lat'     => 13.6989,
            'pickup_lng'     => -89.1914,
        ]));

        $this->assertSame('Hotel Real, San Salvador', $prepared['pickup_address']);
        $this->assertSame(13.6989, (float) $prepared['pickup_lat']);
        $this->assertSame(-89.1914, (float) $prepared['pickup_lng']);
    }

    public function test_pickup_point_defaults_to_null(): void
    {
        $tour = $this->createTour();
        $prepared = $this->handler->prepare($this->bookingData($tour));

        $this->assertNull($prepared['pickup_address']);
        $this->assertNull($prepared['pickup_lat']);
        $this->assertNull($prepared['pickup_lng']);
    }

    public function test_min_advance_days_is_enforced(): void
    {
        $tour = $this->createTour(['min_advance_days' => 5]);

        $this->expectException(ValidationException::class);
        $this->handler->validate($this->bookingData($tour, ['booking_date' => now()->addDay()->toDateString()]));
    }

    public function test_min_advance_days_allows_far_enough_date(): void
    {
        $tour = $this->createTour(['min_advance_days' => 5]);

        $this->handler->validate($this->bookingData($tour, ['booking_date' => now()->addDays(6)->toDateString()]));
        $this->assertTrue(true); // no exception
    }
}
