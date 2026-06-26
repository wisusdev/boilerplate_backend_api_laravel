<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourAvailability;
use App\Models\User;
use App\Services\TourAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private TourAvailabilityService $service;

    private User $user;

    private const TEST_DATE = '2026-08-15';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TourAvailabilityService();
        $this->user = User::create([
            'username'   => 'availability_tester',
            'first_name' => 'Availability',
            'last_name'  => 'Tester',
            'email'      => 'availability@example.com',
            'password'   => bcrypt('password123'),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createTour(int $capacity = 10): Tour
    {
        return Tour::create([
            'title'        => 'Test Tour',
            'description'  => 'Description',
            'price'        => 50,
            'max_capacity' => $capacity,
            'location'     => 'San Salvador',
            'currency_code' => 'USD',
            'is_active'    => true,
        ]);
    }

    private function createBooking(Tour $tour, int $partySize, string $status, string $date = self::TEST_DATE): Booking
    {
        return Booking::create([
            'user_id'       => $this->user->id,
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'starts_at'     => $date . ' 00:00:00',
            'party_size'    => $partySize,
            'total_price'   => 50 * $partySize,
            'currency_code' => 'USD',
            'status'        => $status,
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_returns_full_capacity_when_no_bookings_exist(): void
    {
        $tour = $this->createTour(10);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(10, $available);
    }

    public function test_subtracts_pending_bookings_from_capacity(): void
    {
        $tour = $this->createTour(10);
        $this->createBooking($tour, 3, Booking::STATUS_PENDING);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(7, $available);
    }

    public function test_subtracts_confirmed_bookings_from_capacity(): void
    {
        $tour = $this->createTour(10);
        $this->createBooking($tour, 4, Booking::STATUS_CONFIRMED);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(6, $available);
    }

    public function test_does_not_count_cancelled_bookings(): void
    {
        $tour = $this->createTour(10);
        $this->createBooking($tour, 8, Booking::STATUS_CANCELLED);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(10, $available);
    }

    public function test_accumulates_multiple_bookings(): void
    {
        $tour = $this->createTour(10);
        $this->createBooking($tour, 3, Booking::STATUS_PENDING);
        $this->createBooking($tour, 4, Booking::STATUS_CONFIRMED);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(3, $available);
    }

    public function test_returns_zero_when_tour_is_fully_booked(): void
    {
        $tour = $this->createTour(5);
        $this->createBooking($tour, 5, Booking::STATUS_CONFIRMED);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(0, $available);
    }

    public function test_ignores_bookings_for_different_dates(): void
    {
        $tour = $this->createTour(10);
        $this->createBooking($tour, 9, Booking::STATUS_CONFIRMED, '2026-09-01');

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(10, $available);
    }

    public function test_returns_zero_when_availability_is_closed_for_date(): void
    {
        $tour = $this->createTour(10);
        TourAvailability::create([
            'tour_id'        => $tour->id,
            'available_date' => self::TEST_DATE,
            'is_closed'      => true,
        ]);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(0, $available);
    }

    public function test_uses_capacity_override_instead_of_max_capacity(): void
    {
        $tour = $this->createTour(20);
        TourAvailability::create([
            'tour_id'           => $tour->id,
            'available_date'    => self::TEST_DATE,
            'capacity_override' => 5,
            'is_closed'         => false,
        ]);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(5, $available);
    }

    public function test_capacity_override_is_reduced_by_bookings(): void
    {
        $tour = $this->createTour(20);
        TourAvailability::create([
            'tour_id'           => $tour->id,
            'available_date'    => self::TEST_DATE,
            'capacity_override' => 5,
            'is_closed'         => false,
        ]);
        $this->createBooking($tour, 2, Booking::STATUS_CONFIRMED);

        $available = $this->service->availableCapacity($tour, self::TEST_DATE);

        $this->assertSame(3, $available);
    }
}
