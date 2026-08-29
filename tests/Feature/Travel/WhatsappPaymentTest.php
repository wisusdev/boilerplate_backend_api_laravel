<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Notifications\BookingReceiptNotification;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Pago asistido por WhatsApp: sin pasarela configurada, la solicitud de compra
 * se deriva a un agente que acompaña al cliente.
 */
class WhatsappPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        SiteSettings::flush();
    }

    protected function tearDown(): void
    {
        SiteSettings::flush();
        parent::tearDown();
    }

    private function paymentSettings(array $overrides = []): void
    {
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode(array_merge([
            'default_currency' => 'USD',
            'payment_whatsapp_enabled' => true,
            'payment_whatsapp_number' => '+503 7000 1234',
            'stripe_secret_key' => 'sk_live_no_debe_salir',
        ], $overrides))]);

        SiteSettings::flush();
    }

    private function user(?string $role = null, string $p = 'u'): User
    {
        $user = User::create([
            'username' => $p.uniqid(),
            'first_name' => 'Ana', 'last_name' => 'Cliente',
            'email' => uniqid($p).'@example.com',
            'password' => bcrypt('password123'),
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function booking(User $user): Booking
    {
        $tour = Tour::create([
            'title' => 'Volcán Santa Ana', 'description' => 'd', 'price' => 40,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => '2026-12-05 00:00:00', 'party_size' => 2,
            'total_price' => 80, 'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── Ajustes visibles para el cliente ─────────────────────────────────────

    public function test_un_cliente_ve_los_metodos_de_pago_disponibles(): void
    {
        $this->paymentSettings(['payment_bank_transfer_enabled' => true, 'payment_bank_name' => 'Banco Agrícola']);
        Passport::actingAs($this->user());

        $attrs = $this->apiJson('GET', '/api/v1/settings')->assertOk()->json('data.attributes');

        // Antes, las claves payment_* solo llegaban a los administradores y el
        // paso de pago quedaba vacío para todos los clientes.
        $this->assertTrue($attrs['payment_whatsapp_enabled']);
        $this->assertTrue($attrs['payment_bank_transfer_enabled']);
        $this->assertSame('Banco Agrícola', $attrs['payment_bank_name']);
    }

    public function test_los_ajustes_publicos_no_exponen_credenciales_de_pasarela(): void
    {
        $this->paymentSettings();
        Passport::actingAs($this->user());

        $attrs = $this->apiJson('GET', '/api/v1/settings')->assertOk()->json('data.attributes');

        $this->assertArrayNotHasKey('stripe_secret_key', $attrs);
        $this->assertStringNotContainsString('sk_live_no_debe_salir', json_encode($attrs));
    }

    public function test_un_visitante_sin_sesion_tambien_ve_los_metodos(): void
    {
        $this->paymentSettings();

        $attrs = $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/settings')->assertOk()->json('data.attributes');

        $this->assertTrue($attrs['payment_whatsapp_enabled']);
        $this->assertArrayNotHasKey('stripe_secret_key', $attrs);
    }

    public function test_la_estructura_heredada_del_seeder_tambien_es_visible(): void
    {
        // Lo que hay en producción: payment_methods anidado, no claves planas.
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode([
            'payment_methods' => [
                'stripe' => ['enabled' => true, 'mode' => 'sandbox', 'key' => 'pk_x', 'secret' => 'sk_secreto'],
            ],
            'payment_whatsapp_enabled' => true,
        ])]);
        SiteSettings::flush();

        $attrs = $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/settings')->assertOk()->json('data.attributes');

        $this->assertTrue($attrs['payment_stripe_enabled']);
        $this->assertTrue($attrs['payment_whatsapp_enabled']);
        $this->assertStringNotContainsString('sk_secreto', json_encode($attrs));
    }

    // ─── Enlace de WhatsApp ───────────────────────────────────────────────────

    public function test_el_enlace_lleva_el_detalle_de_la_reserva(): void
    {
        $this->paymentSettings();
        $user = $this->user();
        $booking = $this->booking($user);
        Passport::actingAs($user);

        $attrs = $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertOk()->json('data.attributes');

        $this->assertSame('50370001234', $attrs['phone']);
        $this->assertStringStartsWith('https://wa.me/50370001234?text=', $attrs['url']);

        $mensaje = urldecode(explode('?text=', $attrs['url'])[1]);
        $this->assertStringContainsString('*RESERVA #'.$booking->id.'*', $mensaje);
        $this->assertStringContainsString('Volcán Santa Ana', $mensaje);
        // El total lo pone el servidor, no el navegador.
        $this->assertStringContainsString('*TOTAL: 80.00 USD*', $mensaje);
        $this->assertStringContainsString('Personas: 2', $mensaje);
    }

    public function test_el_mensaje_incluye_extras_enlaces_y_datos_del_cliente(): void
    {
        config(['app.frontend_url' => 'https://cuscaadventure.com']);
        $this->paymentSettings();
        $user = $this->user();
        $user->update(['phone' => '+503 7777 8888']);

        $booking = $this->booking($user);
        $booking->update([
            'service_fees' => [
                ['name' => 'Desayuno', 'amount' => 5, 'calc' => 'per_person', 'total' => 10],
                ['name' => 'Entrada al parque', 'amount' => 7, 'calc' => 'fixed', 'total' => 7],
            ],
            'upgrade_label' => 'Sedán privado',
            'upgrade_surcharge' => 20,
            'pickup_address' => 'Col. Escalón, San Salvador',
            'notes' => 'Somos vegetarianos',
        ]);

        Passport::actingAs($user);

        $url = $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertOk()->json('data.attributes.url');
        $mensaje = urldecode(explode('?text=', $url)[1]);

        // Enlace a la ficha pública: el agente abre el producto y ve el detalle.
        $this->assertStringContainsString('https://cuscaadventure.com/tours/'.$booking->bookable_id, $mensaje);
        // Atajo al panel para gestionar la reserva ya creada.
        $this->assertStringContainsString('/admin/tours?tab=agendados', $mensaje);
        // Qué contrató exactamente.
        $this->assertStringContainsString('Desayuno (x2): 10.00 USD', $mensaje);
        $this->assertStringContainsString('Entrada al parque: 7.00 USD', $mensaje);
        $this->assertStringContainsString('Sedán privado (+20.00 USD)', $mensaje);
        $this->assertStringContainsString('Col. Escalón, San Salvador', $mensaje);
        $this->assertStringContainsString('Somos vegetarianos', $mensaje);
        $this->assertStringContainsString('Estado: pendiente de pago', $mensaje);
        // Con quién hablar.
        $this->assertStringContainsString($user->email, $mensaje);
        $this->assertStringContainsString('+503 7777 8888', $mensaje);
    }

    public function test_el_mensaje_de_transporte_lleva_su_propio_detalle(): void
    {
        config(['app.frontend_url' => 'https://cuscaadventure.com']);
        $this->paymentSettings();
        $user = $this->user();

        $vehiculo = TransportVehicle::create([
            'title' => 'Toyota Hilux', 'description' => 'd', 'vehicle_type' => 'pickup',
            'daily_rate' => 60, 'hourly_rate' => 10, 'capacity' => 5, 'location' => 'San Salvador',
            'currency_code' => 'USD', 'is_active' => true,
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'bookable_type' => TransportVehicle::class, 'bookable_id' => $vehiculo->id,
            'starts_at' => '2026-12-05 09:00:00', 'ends_at' => '2026-12-07 09:00:00',
            'party_size' => 1, 'total_price' => 120, 'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
        ]);
        $booking->transportDetail()->create([
            'pickup_location' => 'Aeropuerto', 'dropoff_location' => 'Hotel Real', 'rental_type' => 'daily',
        ]);

        Passport::actingAs($user);

        $url = $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertOk()->json('data.attributes.url');
        $mensaje = urldecode(explode('?text=', $url)[1]);

        $this->assertStringContainsString('Vehículo: Toyota Hilux', $mensaje);
        $this->assertStringContainsString('https://cuscaadventure.com/transport/'.$vehiculo->id, $mensaje);
        $this->assertStringContainsString('Recogida: 05/12/2026 09:00', $mensaje);
        $this->assertStringContainsString('Devolución: 07/12/2026 09:00', $mensaje);
        $this->assertStringContainsString('Desde: Aeropuerto', $mensaje);
        $this->assertStringContainsString('Modalidad: por día', $mensaje);
        $this->assertStringContainsString('/admin/vehicles?tab=reservas', $mensaje);
    }

    public function test_se_envia_el_comprobante_en_pdf_al_cliente(): void
    {
        Notification::fake();
        $this->paymentSettings();
        $user = $this->user();
        $booking = $this->booking($user);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertOk()
            ->assertJsonPath('data.attributes.receipt_sent', true);

        Notification::assertSentTo($user, BookingReceiptNotification::class);
    }

    public function test_el_comprobante_es_un_pdf_con_la_referencia(): void
    {
        $this->paymentSettings();
        $user = $this->user();
        $booking = $this->booking($user);

        $mail = (new BookingReceiptNotification($booking, 'https://wa.me/50370001234'))->toMail($user);
        $array = $mail->toArray();

        $this->assertSame('Tu reserva #'.$booking->id.' está apartada', $array['subject']);
        $this->assertStringContainsString('#'.$booking->id, json_encode($array['introLines']));

        $adjunto = $mail->rawAttachments[0] ?? null;
        $this->assertNotNull($adjunto, 'El correo debe llevar el comprobante adjunto.');
        $this->assertSame('comprobante-reserva-'.$booking->id.'.pdf', $adjunto['name']);
        // Firma de un PDF real, no una plantilla vacía.
        $this->assertStringStartsWith('%PDF', $adjunto['data']);
    }

    public function test_usa_el_whatsapp_de_contacto_si_no_hay_numero_de_pagos(): void
    {
        $this->paymentSettings(['payment_whatsapp_number' => '']);
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode(['contact_whatsapp' => '503 6000 9999'])]);
        SiteSettings::flush();

        $user = $this->user();
        $booking = $this->booking($user);
        Passport::actingAs($user);

        $attrs = $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertOk()->json('data.attributes');

        $this->assertSame('50360009999', $attrs['phone']);
    }

    public function test_falla_si_no_hay_ningun_numero_configurado(): void
    {
        $this->paymentSettings(['payment_whatsapp_number' => '']);
        $user = $this->user();
        $booking = $this->booking($user);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertStatus(422);
    }

    public function test_falla_si_el_metodo_esta_desactivado(): void
    {
        $this->paymentSettings(['payment_whatsapp_enabled' => false]);
        $user = $this->user();
        $booking = $this->booking($user);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertStatus(422);
    }

    public function test_no_se_puede_pedir_el_enlace_de_una_reserva_ajena(): void
    {
        $this->paymentSettings();
        $booking = $this->booking($this->user(null, 'dueno'));
        Passport::actingAs($this->user(null, 'intruso'));

        $this->apiJson('POST', '/api/v1/bookings/'.$booking->id.'/whatsapp-link')
            ->assertForbidden();
    }

    // ─── Registro del pago ────────────────────────────────────────────────────

    public function test_el_pago_por_whatsapp_queda_pendiente_con_el_importe_del_servidor(): void
    {
        $this->paymentSettings();
        $user = $this->user();
        $booking = $this->booking($user); // total_price = 80
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/payments', [
            'data' => ['type' => 'payments', 'attributes' => [
                'payable_type' => 'booking',
                'payable_id' => $booking->id,
                'gateway' => 'whatsapp',
                'method' => 'whatsapp',
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('payments', [
            'payable_id' => $booking->id,
            'gateway' => 'whatsapp',
            'amount' => 80.00,
            'status' => 'pending',
        ]);
    }

    public function test_el_cliente_no_puede_darse_por_pagado_via_whatsapp(): void
    {
        $this->paymentSettings();
        $user = $this->user();
        $booking = $this->booking($user);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/payments', [
            'data' => ['type' => 'payments', 'attributes' => [
                'payable_type' => 'booking',
                'payable_id' => $booking->id,
                'gateway' => 'whatsapp',
                'status' => 'paid',
            ]],
        ])->assertCreated();

        $this->assertSame('pending', Payment::first()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }
}
