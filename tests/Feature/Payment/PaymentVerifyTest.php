<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Services\PaypalService;
use App\Services\StripeService;
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
            'username' => 'verifier',
            'first_name' => 'Verify',
            'last_name' => 'User',
            'email' => 'verifier@example.com',
            'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        return $user;
    }

    private function createPendingPayment(User $user, string $gateway, string $transactionRef): Payment
    {
        $tour = Tour::create([
            'title' => 'Trekking Tour',
            'description' => 'Mountain walk',
            'price' => 60,
            'max_capacity' => 6,
            'location' => 'Suchitoto',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'starts_at' => '2026-11-01 00:00:00',
            'party_size' => 1,
            'total_price' => 60,
            'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
            'user_id' => $user->id,
        ]);

        return Payment::create([
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'gateway' => $gateway,
            'method' => $gateway,
            'amount' => 60.00,
            'currency_code' => 'USD',
            'status' => 'pending',
            'transaction_reference' => $transactionRef,
        ]);
    }

    /**
     * Respuesta de captura de PayPal con el importe en el bloque de la captura.
     */
    private function paypalCapture(string $orderId, string $value): array
    {
        return [
            'id' => $orderId,
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE_'.$orderId,
                    'amount' => ['value' => $value, 'currency_code' => 'USD'],
                ]]],
            ]],
        ];
    }

    private function mockStripeIntent(string $intentId, string $status, int $amountReceived): void
    {
        $this->mock(StripeService::class, function ($mock) use ($intentId, $status, $amountReceived) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->with($intentId)
                ->andReturn([
                    'id' => $intentId,
                    'status' => $status,
                    'amount_received' => $amountReceived,
                    'currency' => 'USD',
                ]);
            $mock->shouldReceive('toCents')->andReturnUsing(fn ($amount) => (int) round($amount * 100));
        });
    }

    private function verifyPayload(Payment $payment, string $token): array
    {
        return [
            'data' => [
                'type' => 'payment-verify',
                'attributes' => [
                    'gateway' => $payment->gateway,
                    'payment_id' => $payment->id,
                    'token' => $token,
                ],
            ],
        ];
    }

    // ─── PayPal ───────────────────────────────────────────────────────────────

    public function test_verify_paypal_captura_orden_y_marca_pago_como_paid(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_777');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with('PAYPAL_ORDER_777')
                ->andReturn($this->paypalCapture('PAYPAL_ORDER_777', '60.00'));
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'PAYPAL_ORDER_777'));

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertEquals('paid', $attrs['status']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'transaction_reference' => 'PAYPAL_ORDER_777',
        ]);
    }

    public function test_verify_paypal_rechaza_una_captura_de_menor_importe(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_CHEAP');

        // Orden pagada por 1,00 para un pago de 60,00.
        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->andReturn($this->paypalCapture('PAYPAL_ORDER_CHEAP', '1.00'));
        });

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'PAYPAL_ORDER_CHEAP'))
            ->assertStatus(422);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_verify_paypal_ignora_el_token_enviado_por_el_cliente(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_REAL');

        // Se captura la orden guardada en el pago, no la que envía el cliente.
        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with('PAYPAL_ORDER_REAL')
                ->andReturn($this->paypalCapture('PAYPAL_ORDER_REAL', '60.00'));
        });

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'ORDEN_DEL_ATACANTE'))
            ->assertOk();
    }

    public function test_verify_paypal_retorna_failed_cuando_captura_no_es_completed(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'paypal', 'PAYPAL_ORDER_888');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->andReturn([
                    'id' => 'CAPTURE_PENDING',
                    'status' => 'PENDING', // no es COMPLETED
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'PAYPAL_ORDER_888'));

        $response->assertOk();
        $this->assertEquals('failed', $response->json('data.attributes.status'));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending', // no cambió
        ]);
    }

    public function test_verify_paypal_retorna_422_cuando_captura_lanza_excepcion(): void
    {
        $user = $this->createAuthenticatedUser();
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

    public function test_verify_stripe_consulta_el_payment_intent_en_el_servidor(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_test_intent_123');

        // La confirmación de Stripe.js ocurre en el navegador y no prueba nada:
        // el estado real se recupera desde el servidor.
        $this->mockStripeIntent('pi_test_intent_123', 'succeeded', 6000);

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_test_intent_123'));

        $response->assertOk();
        $this->assertEquals('paid', $response->json('data.attributes.status'));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_verify_stripe_no_marca_paid_si_el_intent_no_esta_pagado(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_unpaid');

        $this->mockStripeIntent('pi_unpaid', 'requires_payment_method', 0);

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_unpaid'));

        $response->assertOk();
        $this->assertEquals('failed', $response->json('data.attributes.status'));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_verify_stripe_rechaza_un_cobro_por_menos_importe(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_cheap');

        // Cobrado 1,00 para un pago de 60,00.
        $this->mockStripeIntent('pi_cheap', 'succeeded', 100);

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_cheap'))
            ->assertStatus(422);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_verify_stripe_actualiza_paid_at(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_test_456');

        $this->assertNull($payment->paid_at);
        $this->mockStripeIntent('pi_test_456', 'succeeded', 6000);

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_test_456'));

        $this->assertNotNull($payment->refresh()->paid_at);
    }

    // ─── Wompi ────────────────────────────────────────────────────────────────

    public function test_verify_wompi_no_marca_paid_por_si_solo(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'wompi', 'WOMPI_TXN_444');

        // Tras el 3DS el cliente vuelve a la app, pero la confirmación real llega
        // por el webhook firmado; /verify solo refleja el estado persistido.
        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'WOMPI_TXN_444'));

        $response->assertOk();
        $this->assertEquals('pending', $response->json('data.attributes.status'));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);
    }

    // ─── Casos generales ──────────────────────────────────────────────────────

    public function test_verify_rechaza_un_pago_ajeno(): void
    {
        $owner = User::create([
            'username' => 'owner_verify',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'owner_verify@example.com',
            'password' => bcrypt('password123'),
        ]);
        $payment = $this->createPendingPayment($owner, 'stripe', 'pi_de_otro');

        // Otro usuario autenticado no puede confirmar pagos que no son suyos
        // (payments.id es autoincremental y por tanto enumerable).
        $this->createAuthenticatedUser();

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_de_otro'))
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

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
                'type' => 'payment-verify',
                'attributes' => [
                    'gateway' => 'stripe',
                    'payment_id' => 99999,
                    'token' => 'some_token',
                ],
            ],
        ]);

        $response->assertNotFound();
    }

    public function test_verify_devuelve_id_del_pago_en_respuesta(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'stripe', 'pi_abc');
        $this->mockStripeIntent('pi_abc', 'succeeded', 6000);

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'pi_abc'));

        $response->assertOk();
        $this->assertEquals($payment->id, $response->json('data.id'));
    }
}
