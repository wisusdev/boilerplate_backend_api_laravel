<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createBooking(): Booking
    {
        $user = User::create([
            'username'   => 'tester',
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'tester@example.com',
            'password'   => bcrypt('pass'),
        ]);

        $tour = Tour::create([
            'title'        => 'Test Tour',
            'description'  => 'Desc',
            'price'        => 80,
            'max_capacity' => 10,
            'location'     => 'San Salvador',
            'currency_code' => 'USD',
            'is_active'    => true,
        ]);

        return Booking::create([
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'booking_type'  => Booking::TYPE_TOUR,
            'starts_at'     => '2026-09-01 00:00:00',
            'party_size'    => 2,
            'total_price'   => 160,
            'currency_code' => 'USD',
            'status'        => Booking::STATUS_PENDING,
            'user_id'       => $user->id,
        ]);
    }

    // ─── create() ─────────────────────────────────────────────────────────────

    public function test_create_crea_pago_con_estado_pending(): void
    {
        $booking = $this->createBooking();

        $payment = $this->service->create($booking, [
            'gateway'       => 'paypal',
            'method'        => 'paypal',
            'amount'        => 160.00,
            'currency_code' => 'USD',
            'status'        => 'pending',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals('paypal', $payment->gateway);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals(160.00, (float) $payment->amount);
        $this->assertNull($payment->paid_at);
        $this->assertDatabaseHas('payments', [
            'gateway' => 'paypal',
            'status'  => 'pending',
        ]);
    }

    public function test_create_con_status_paid_asigna_paid_at(): void
    {
        $booking = $this->createBooking();

        $payment = $this->service->create($booking, [
            'gateway'       => 'manual',
            'method'        => 'cash',
            'amount'        => 80.00,
            'currency_code' => 'USD',
            'status'        => 'paid',
        ]);

        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_create_con_status_pending_no_asigna_paid_at(): void
    {
        $booking = $this->createBooking();

        $payment = $this->service->create($booking, [
            'gateway'       => 'stripe',
            'method'        => 'card',
            'amount'        => 50.00,
            'currency_code' => 'USD',
            'status'        => 'pending',
        ]);

        $this->assertNull($payment->paid_at);
    }

    public function test_create_guarda_transaction_reference(): void
    {
        $booking = $this->createBooking();

        $payment = $this->service->create($booking, [
            'gateway'               => 'paypal',
            'amount'                => 100.00,
            'currency_code'         => 'USD',
            'status'                => 'pending',
            'transaction_reference' => 'PAYPAL_ORDER_XYZ123',
        ]);

        $this->assertEquals('PAYPAL_ORDER_XYZ123', $payment->transaction_reference);
    }

    public function test_create_establece_relacion_polimorfca_con_booking(): void
    {
        $booking = $this->createBooking();

        $payment = $this->service->create($booking, [
            'gateway'       => 'manual',
            'amount'        => 80.00,
            'currency_code' => 'USD',
            'status'        => 'pending',
        ]);

        $this->assertEquals(Booking::class, $payment->payable_type);
        $this->assertEquals($booking->id, $payment->payable_id);
    }

    public function test_create_usa_usd_como_moneda_por_defecto(): void
    {
        $booking = $this->createBooking();

        $payment = $this->service->create($booking, [
            'gateway' => 'manual',
            'amount'  => 50.00,
            'status'  => 'pending',
        ]);

        $this->assertEquals('USD', $payment->currency_code);
    }

    // ─── markPaid() ───────────────────────────────────────────────────────────

    public function test_mark_paid_cambia_estado_a_paid(): void
    {
        $booking = $this->createBooking();
        $payment = $this->service->create($booking, [
            'gateway' => 'paypal',
            'amount'  => 160.00,
            'status'  => 'pending',
        ]);

        $updated = $this->service->markPaid($payment, 'CAPTURE_ABC');

        $this->assertEquals('paid', $updated->status);
    }

    public function test_mark_paid_asigna_paid_at(): void
    {
        Carbon::setTestNow('2026-09-10 15:00:00');
        $booking = $this->createBooking();
        $payment = $this->service->create($booking, [
            'gateway' => 'stripe',
            'amount'  => 100.00,
            'status'  => 'pending',
        ]);

        $updated = $this->service->markPaid($payment);

        $this->assertNotNull($updated->paid_at);
        Carbon::setTestNow();
    }

    public function test_mark_paid_actualiza_transaction_reference(): void
    {
        $booking = $this->createBooking();
        $payment = $this->service->create($booking, [
            'gateway'               => 'paypal',
            'amount'                => 160.00,
            'status'                => 'pending',
            'transaction_reference' => 'OLD_ORDER_ID',
        ]);

        $updated = $this->service->markPaid($payment, 'NEW_CAPTURE_ID');

        $this->assertEquals('NEW_CAPTURE_ID', $updated->transaction_reference);
    }

    public function test_mark_paid_guarda_payload_del_gateway(): void
    {
        $booking = $this->createBooking();
        $payment = $this->service->create($booking, [
            'gateway' => 'paypal',
            'amount'  => 160.00,
            'status'  => 'pending',
        ]);

        $gatewayResponse = [
            'id'     => 'CAPTURE_123',
            'status' => 'COMPLETED',
            'payer'  => ['email_address' => 'buyer@example.com'],
        ];

        $updated = $this->service->markPaid($payment, 'CAPTURE_123', $gatewayResponse);

        $this->assertIsArray($updated->payload);
        $this->assertEquals('COMPLETED', $updated->payload['status']);
    }

    public function test_mark_paid_preserva_transaction_reference_anterior_si_no_se_pasa_nueva(): void
    {
        $booking = $this->createBooking();
        $payment = $this->service->create($booking, [
            'gateway'               => 'wompi',
            'amount'                => 80.00,
            'status'                => 'pending',
            'transaction_reference' => 'WOMPI_TXN_999',
        ]);

        $updated = $this->service->markPaid($payment, null);

        $this->assertEquals('WOMPI_TXN_999', $updated->transaction_reference);
    }
}
