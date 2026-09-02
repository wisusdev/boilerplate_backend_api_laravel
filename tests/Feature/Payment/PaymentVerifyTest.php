<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Services\WompiService;
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

    // ─── Wompi ────────────────────────────────────────────────────────────────

    public function test_verify_wompi_consulta_el_enlace_y_marca_paid(): void
    {
        $user = $this->createAuthenticatedUser();
        // La referencia guardada es el id del enlace de pago.
        $payment = $this->createPendingPayment($user, 'wompi', '55123');

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('getPaymentLinkResult')
                ->once()
                ->with('55123')
                ->andReturn([
                    'paid' => true, 'amount' => 60.0,
                    'transaction_id' => 'WOMPI-TXN-777', 'external_id' => 'pago-1', 'message' => null,
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'ignorado'));

        $response->assertOk();
        $this->assertEquals('paid', $response->json('data.attributes.status'));
        // Pasa a referenciar la transacción real, que es la que llega por webhook.
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id, 'status' => 'paid', 'transaction_reference' => 'WOMPI-TXN-777',
        ]);
    }

    public function test_verify_wompi_no_marca_paid_si_la_transaccion_no_fue_aprobada(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'wompi', '55124');

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('getPaymentLinkResult')
                ->once()
                ->andReturn([
                    'paid' => false, 'amount' => 0.0,
                    'transaction_id' => 'WOMPI-TXN-RECHAZADA', 'external_id' => null, 'message' => 'Tarjeta rechazada',
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'x'));

        $response->assertOk();
        $this->assertEquals('failed', $response->json('data.attributes.status'));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_verify_wompi_sigue_pendiente_si_el_cliente_aun_no_ha_pagado(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'wompi', '55126');

        // Enlace creado pero sin transacción todavía: no es un fallo.
        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('getPaymentLinkResult')
                ->once()
                ->andReturn(['paid' => false, 'amount' => 0.0, 'transaction_id' => null, 'external_id' => null, 'message' => null]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'x'));

        $response->assertOk();
        $this->assertEquals('pending', $response->json('data.attributes.status'));
    }

    public function test_verify_wompi_rechaza_un_cobro_por_menos_importe(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'wompi', '55125'); // pago de 60.00

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('getPaymentLinkResult')
                ->once()
                ->andReturn([
                    'paid' => true, 'amount' => 1.0,
                    'transaction_id' => 'WOMPI-TXN-BARATO', 'external_id' => null, 'message' => null,
                ]);
        });

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'x'))
            ->assertStatus(422);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_verify_wompi_actualiza_paid_at(): void
    {
        $user = $this->createAuthenticatedUser();
        $payment = $this->createPendingPayment($user, 'wompi', '55127');
        $this->assertNull($payment->paid_at);

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('getPaymentLinkResult')
                ->once()
                ->andReturn(['paid' => true, 'amount' => 60.0, 'transaction_id' => 'WOMPI-TXN-999', 'external_id' => null, 'message' => null]);
        });

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'x'));

        $this->assertNotNull($payment->refresh()->paid_at);
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
        $payment = $this->createPendingPayment($owner, 'wompi', 'wompi_de_otro');

        // Otro usuario autenticado no puede confirmar pagos que no son suyos
        // (payments.id es autoincremental y por tanto enumerable).
        $this->createAuthenticatedUser();

        $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'wompi_de_otro'))
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_verify_requiere_autenticacion(): void
    {
        $response = $this->postJsonApi('/api/v1/payments/verify', [
            'data' => ['type' => 'payment-verify', 'attributes' => ['gateway' => 'wompi', 'payment_id' => 1, 'token' => 'xyz']],
        ]);

        $response->assertUnauthorized();
    }

    public function test_verify_rechaza_un_gateway_no_soportado(): void
    {
        $this->createAuthenticatedUser();

        // paypal/stripe se retiraron del producto.
        $response = $this->postJsonApi('/api/v1/payments/verify', [
            'data' => ['type' => 'payment-verify', 'attributes' => ['gateway' => 'paypal', 'payment_id' => 1, 'token' => 'xyz']],
        ]);

        $response->assertStatus(422);
    }

    public function test_verify_retorna_404_cuando_pago_no_existe(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJsonApi('/api/v1/payments/verify', [
            'data' => [
                'type' => 'payment-verify',
                'attributes' => [
                    'gateway' => 'wompi',
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
        $payment = $this->createPendingPayment($user, 'wompi', 'wompi_abc');

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('getPaymentLinkResult')
                ->once()
                ->andReturn(['paid' => true, 'amount' => 60.0, 'transaction_id' => 'WOMPI-TXN-ABC', 'external_id' => null, 'message' => null]);
        });

        $response = $this->postJsonApi('/api/v1/payments/verify', $this->verifyPayload($payment, 'wompi_abc'));

        $response->assertOk();
        $this->assertEquals($payment->id, $response->json('data.id'));
    }
}
