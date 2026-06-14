<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }

    private function apiJson(string $method, string $uri, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            $method,
            $uri,
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.api+json',
                'CONTENT_TYPE' => 'application/vnd.api+json',
            ],
            json_encode($payload)
        );
    }

    public function test_store_rejects_when_capacity_is_exceeded(): void
    {
        $user = User::create([
            'username' => 'traveler1',
            'first_name' => 'Traveler',
            'last_name' => 'One',
            'email' => 'traveler1@example.com',
            'password' => bcrypt('password123'),
        ]);

        Passport::actingAs($user);

        $tour = Tour::create([
            'title' => 'Lake tour',
            'description' => 'Boat',
            'price' => 50,
            'max_capacity' => 2,
            'location' => 'Coatepeque',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        Booking::create([
            'user_id'       => $user->id,
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'booking_type'  => Booking::TYPE_TOUR,
            'starts_at'     => '2099-06-01 00:00:00',
            'party_size'    => 2,
            'total_price'   => 100,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_PENDING,
        ]);

        $response = $this->apiJson('POST', '/api/v1/bookings', [
            'data' => [
                'type' => 'bookings',
                'attributes' => [
                    'booking_type' => 'tour',
                    'tour_id' => $tour->id,
                    'booking_date' => '2099-06-01',
                    'pax_count' => 1,
                ],
            ],
        ]);

        $response->assertJsonApiValidationErrors('data.attributes.pax_count');
    }

    public function test_confirmed_booking_creates_invoice(): void
    {
        $user = User::create([
            'username' => 'traveler2',
            'first_name' => 'Traveler',
            'last_name' => 'Two',
            'email' => 'traveler2@example.com',
            'password' => bcrypt('password123'),
        ]);

        Passport::actingAs($user);

        $tour = Tour::create([
            'title' => 'Coffee route',
            'description' => 'Coffee farms',
            'price' => 40,
            'max_capacity' => 10,
            'location' => 'Santa Ana',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id'       => $user->id,
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'booking_type'  => Booking::TYPE_TOUR,
            'starts_at'     => '2026-06-05 00:00:00',
            'party_size'    => 2,
            'total_price'   => 80,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_PENDING,
        ]);

        $response = $this->apiJson('PATCH', '/api/v1/bookings/' . $booking->id, [
            'data' => [
                'id' => (string) $booking->id,
                'type' => 'bookings',
                'attributes' => [
                    'status' => Booking::STATUS_CONFIRMED,
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'amount' => 80,
        ]);
    }
}