<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Services\PaypalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PaymentVerifyTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createAuthenticatedUser(): User
    {
        $user = User::create([
            'username'   => 'verifier',
            'first_name' => 'Verify',
            'last_name'  => 'User',
            'email'      => 'verifier@example.com',
            'password'   => bcrypt('password123'),
        ]);
        Passport::actingAs($user);
        return $user;
    }

    private function createPendingPayment(User $user, string $gateway, string $transactionRef): Payment
    {
        $tour = Tour::create([
            'title'        => 'Trekking Tour',
            'description'  => 'Mountain walk',
            'price'        => 60,
            'max_capacity' => 6,
            'location'     => 'Suchitoto',
            'currency_code' => 'USD',
            'is_active'    => true,
        ]);

        $booking = Booking::create([
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'starts_at'     => '2026-11-01 00:00:00',
            'party_size'    => 1,
            'total_price'   => 60,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_PENDING,
            'user_id'       => $user->id,
        ]);

        return Payment::create([
            'payable_type'          => Booking::class,
            'payable_id'            => $booking->id,
            'gateway'               => $gateway,
            'method'                => $gateway,
            'amount'                => 60.00,
            'currency_code'         => 'USD',
            'status'                => 'pending',
            'transaction_reference' => $transactionRef,
        ]);
    }

    private function verifyPayload(Payment $payment, string $token): array
    {
        return [
            'data' => [
                'type'       => 'payment-verify',
                'attributes' => [
                    'gateway'    => $payment->gateway,
                    'payment_id' => $payment->id,
                    'token'      => $token,
                ],
            ],
        ];
    }

    // ─── PayPal ───────────────────────────────────────────────────────────────

    public function test_verify_paypal_captura_orden_y_marca_pago_como_paid(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_777');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with('PAYPAL_ORDER_777')
                ->andReturn([
                    'id'     => 'CAPTURE_PAYPAL_001',
                    'status' => 'COMPLETED',
                    'payer'  => ['email_address' => 'buyer@paypal.com'],
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'PAYPAL_ORDER_777'));

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertEquals('paid', $attrs['status']);
        $this->assertEquals('CAPTURE_PAYPAL_001', $attrs['transaction_reference']);

        $this->assertDatabaseHas('payments', [
            'id'                    => $payment->id,
            'status'                => 'paid',
            'transaction_reference' => 'CAPTURE_PAYPAL_001',
        ]);
    }

    public function test_verify_paypal_retorna_failed_cuando_captura_no_es_completed(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_888');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->andReturn([
                    'id'     => 'CAPTURE_PENDING',
                    'status' => 'PENDING', // no es COMPLETED
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'PAYPAL_ORDER_888'));

        $response->assertOk();
        $this->assertEquals('failed', $response->json('data.attributes.status'));

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'pending', // no cambió
        ]);
    }

    public function test_verify_paypal_retorna_422_cuando_captura_lanza_excepcion(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_ERR');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->andThrow(new \RuntimeException('Order already captured'));
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'PAYPAL_ORDER_ERR'));

        $response->assertStatus(422);
        $this->assertStringContainsString('Order already captured', $response->json('error'));
    }

    // ─── Stripe ───────────────────────────────────────────────────────────────

    public function test_verify_stripe_marca_pago_como_paid_sin_llamada_externa(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_test_intent_123');

        // Stripe no debería hacer ninguna llamada HTTP desde el servidor
        // (la confirmación ya ocurrió en el cliente con Stripe.js)
        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_test_intent_123'));

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertEquals('paid', $attrs['status']);

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'paid',
        ]);
    }

    public function test_verify_stripe_actualiza_paid_at(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_test_456');

        $this->assertNull($payment->paid_at);

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_test_456'));

        $this->assertNotNull($payment->refresh()->paid_at);
    }

    // ─── Wompi ────────────────────────────────────────────────────────────────

    public function test_verify_wompi_marca_pago_como_paid_tras_3ds(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'wompi', 'WOMPI_TXN_444');

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'WOMPI_TXN_444'));

        $response->assertOk();
        $this->assertEquals('paid', $response->json('data.attributes.status'));

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'paid',
        ]);
    }

    // ─── Casos generales ──────────────────────────────────────────────────────

    public function test_verify_requiere_autenticacion(): void
    {
        $response = $this->postJsonApi('/api/v1/payments/verify', [
            'data' => ['type' => 'payment-verify', 'attributes' => ['gateway' => 'paypal', 'payment_id' => 1, 'token' => 'xyz']],
        ]);

        $response->assertUnauthorized();
    }

    public function test_verify_retorna_404_cuando_pago_no_existe(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJsonApi('/api/v1/payments/verify', [
            'data' => [
                'type'       => 'payment-verify',
                'attributes' => [
                    'gateway'    => 'stripe',
                    'payment_id' => 99999,
                    'token'      => 'some_token',
                ],
            ],
        ]);

        $response->assertNotFound();
    }

    public function test_verify_devuelve_id_del_pago_en_respuesta(): void
    {
        $user    = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_abc');

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_abc'));

        $response->assertOk();
        $this->assertEquals($payment->id, $response->json('data.id'));
    }
}
