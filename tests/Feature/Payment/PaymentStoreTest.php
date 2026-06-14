<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PaymentStoreTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createAuthenticatedUser(): User
    {
        $user = User::create([
            'username'   => 'payer',
            'first_name' => 'Pay',
            'last_name'  => 'Er',
            'email'      => 'payer@example.com',
            'password'   => bcrypt('password123'),
        ]);
        Passport::actingAs($user);
        return $user;
    }

    private function createBookingForUser(User $user): Booking
    {
        $tour = Tour::create([
            'title'        => 'Volcano Tour',
            'description'  => 'Great tour',
            'price'        => 75,
            'max_capacity' => 10,
            'location'     => 'Santa Ana',
            'currency_code' => 'USD',
            'is_active'    => true,
        ]);

        return Booking::create([
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'booking_type'  => Booking::TYPE_TOUR,
            'starts_at'     => '2026-10-01 00:00:00',
            'party_size'    => 2,
            'total_price'   => 150,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_PENDING,
            'user_id'       => $user->id,
        ]);
    }

    private function storePayload(int $bookingId, array $overrides = []): array
    {
        return array_replace_recursive([
            'data' => [
                'type'       => 'payments',
                'attributes' => array_merge([
                    'payable_type' => 'booking',
                    'payable_id'   => $bookingId,
                    'gateway'      => 'manual',
                    'method'       => 'cash',
                    'amount'       => 150.00,
                    'currency_code' => 'USD',
                    'status'       => 'paid',
                ], $overrides['data']['attributes'] ?? []),
            ],
        ], $overrides);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_store_crea_pago_manual_en_efectivo(): void
    {
        $user    = $this->createAuthenticatedUser();
        $booking = $this->createBookingForUser($user);

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload($booking->id));

        $response->assertCreated();
        $this->assertDatabaseHas('payments', [
            'payable_type' => Booking::class,
            'payable_id'   => $booking->id,
            'gateway'      => 'manual',
            'method'       => 'cash',
            'status'       => 'paid',
        ]);
    }

    public function test_store_crea_pago_manual_por_transferencia(): void
    {
        $user    = $this->createAuthenticatedUser();
        $booking = $this->createBookingForUser($user);

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload($booking->id, [
            'data' => ['attributes' => ['method' => 'bank_transfer']],
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('payments', ['method' => 'bank_transfer']);
    }

    public function test_store_requiere_autenticacion(): void
    {
        $response = $this->postJsonApi('/api/v1/payments', [
            'data' => [
                'type'       => 'payments',
                'attributes' => ['payable_type' => 'booking', 'payable_id' => 1, 'gateway' => 'manual', 'amount' => 10],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_falla_con_gateway_invalido(): void
    {
        $user    = $this->createAuthenticatedUser();
        $booking = $this->createBookingForUser($user);

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload($booking->id, [
            'data' => ['attributes' => ['gateway' => 'bitcoin']],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_falla_cuando_booking_no_existe(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload(99999));

        $response->assertNotFound();
    }

    public function test_store_falla_con_amount_negativo(): void
    {
        $user    = $this->createAuthenticatedUser();
        $booking = $this->createBookingForUser($user);

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload($booking->id, [
            'data' => ['attributes' => ['amount' => -10]],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_falla_sin_payable_id(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJsonApi('/api/v1/payments', [
            'data' => [
                'type'       => 'payments',
                'attributes' => ['gateway' => 'manual', 'amount' => 100],
            ],
        ]);

        $response->assertJsonApiValidationErrors('data.attributes.payable_id');
    }

    public function test_store_falla_con_payable_type_invalido(): void
    {
        $user    = $this->createAuthenticatedUser();
        $booking = $this->createBookingForUser($user);

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload($booking->id, [
            'data' => ['attributes' => ['payable_type' => 'invoice']],
        ]));

        $response->assertStatus(422);
    }

    public function test_store_retorna_el_recurso_de_pago_creado(): void
    {
        $user    = $this->createAuthenticatedUser();
        $booking = $this->createBookingForUser($user);

        $response = $this->postJsonApi('/api/v1/payments', $this->storePayload($booking->id, [
            'data' => ['attributes' => ['amount' => 75.50]],
        ]));

        $response->assertCreated();
        $responseData = $response->json('data');
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals('payments', $responseData['type']);
    }
}
