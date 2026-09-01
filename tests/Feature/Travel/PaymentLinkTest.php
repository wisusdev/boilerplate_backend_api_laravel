<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\PaymentLinkIssuedNotification;
use App\Services\PaymentService;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Cobro con enlaces de pago del banco (BAC).
 *
 * Sin API del banco la confirmación es un acto humano, así que lo que se prueba
 * aquí no es tanto el camino feliz como las guardas: quién puede confirmar, qué
 * pasa si dos reservas se confirman con la misma autorización y qué ocurre con
 * un cobro tardío o parcial.
 *
 * @see PAGO-ENLACE-BAC.md
 */
class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $this->habilitarEnlaces();
    }

    // ─── Utilidades ───────────────────────────────────────────────────────────

    private function habilitarEnlaces(array $extra = []): void
    {
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode(array_merge([
            'default_currency' => 'USD',
            'payment_bac_link_enabled' => true,
            'payment_bac_link_hosts' => 'baccredomatic.com',
            'payment_bac_link_ttl_hours' => 24,
            'payment_bac_dual_control' => false,
        ], $extra))]);

        SiteSettings::flush();
    }

    private function usuario(string $rol): User
    {
        $user = User::create([
            'username' => uniqid('u'), 'first_name' => 'Ana', 'last_name' => 'Pérez',
            'email' => uniqid('u').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole($rol);

        return $user->fresh();
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'Ruta de las Flores', 'description' => 'd', 'price' => 50,
            'max_capacity' => 10, 'location' => 'Ahuachapán', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function reserva(User $cliente, float $total = 100): Booking
    {
        return Booking::create([
            'user_id' => $cliente->id, 'bookable_type' => Tour::class, 'bookable_id' => $this->tour()->id,
            'starts_at' => now()->addDays(10), 'party_size' => 2, 'total_price' => $total,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function emitir(Booking $booking): PaymentLink
    {
        Passport::actingAs($booking->user);

        $this->apiJson('POST', '/api/v1/payments/checkout', [
            'data' => ['type' => 'payments', 'attributes' => [
                'gateway' => 'bac_link', 'payable_type' => 'booking', 'payable_id' => $booking->id,
            ]],
        ])->assertOk();

        return PaymentLink::query()->latest('id')->firstOrFail();
    }

    private function adjuntar(PaymentLink $link, User $agente, string $url = 'https://pagos.baccredomatic.com/l/abc123'): TestResponse
    {
        Passport::actingAs($agente);

        return $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/attach', [
            'data' => ['type' => 'payment-links', 'attributes' => ['url' => $url]],
        ]);
    }

    private function payloadConfirmacion(array $extra = []): array
    {
        return ['data' => ['type' => 'payment-links', 'attributes' => array_merge([
            'authorization' => '778812',
            'charged' => 100,
            'paid_at' => now()->subHour()->toDateTimeString(),
        ], $extra)]];
    }

    // ─── Emisión ──────────────────────────────────────────────────────────────

    public function test_el_cliente_solicita_un_enlace_y_queda_en_cola(): void
    {
        $booking = $this->reserva($this->usuario('user'));

        Passport::actingAs($booking->user);
        $res = $this->apiJson('POST', '/api/v1/payments/checkout', [
            'data' => ['type' => 'payments', 'attributes' => [
                'gateway' => 'bac_link', 'payable_type' => 'booking', 'payable_id' => $booking->id,
            ]],
        ])->assertOk();

        // Todavía no hay URL: el enlace lo genera una persona en el portal.
        $res->assertJsonPath('data.attributes.status', 'awaiting_link');
        $this->assertNull($res->json('data.attributes.redirect_url'));

        $link = PaymentLink::query()->firstOrFail();
        $this->assertSame(PaymentLink::STATUS_DRAFT, $link->status);
        $this->assertEquals(100.0, (float) $link->amount);
        $this->assertSame('pending', $link->payment->status);
    }

    public function test_pulsar_pagar_dos_veces_no_deja_dos_enlaces_vivos(): void
    {
        $booking = $this->reserva($this->usuario('user'));

        $primero = $this->emitir($booking);
        $segundo = $this->emitir($booking);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, PaymentLink::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_el_importe_sale_del_saldo_y_no_del_cliente(): void
    {
        $booking = $this->reserva($this->usuario('user'), 250);

        Passport::actingAs($booking->user);
        $this->apiJson('POST', '/api/v1/payments/checkout', [
            'data' => ['type' => 'payments', 'attributes' => [
                'gateway' => 'bac_link', 'payable_type' => 'booking', 'payable_id' => $booking->id,
                // Un cliente que intente fijar su propio importe no debe lograrlo.
                'amount' => 1, 'currency_code' => 'USD',
            ]],
        ])->assertOk();

        $this->assertEquals(250.0, (float) PaymentLink::query()->firstOrFail()->amount);
    }

    public function test_con_el_metodo_desactivado_no_se_emite(): void
    {
        $this->habilitarEnlaces(['payment_bac_link_enabled' => false]);
        $booking = $this->reserva($this->usuario('user'));

        Passport::actingAs($booking->user);
        $this->apiJson('POST', '/api/v1/payments/checkout', [
            'data' => ['type' => 'payments', 'attributes' => [
                'gateway' => 'bac_link', 'payable_type' => 'booking', 'payable_id' => $booking->id,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PaymentLink::count());
    }

    public function test_un_cliente_no_ve_el_enlace_de_otro(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));

        Passport::actingAs($this->usuario('user'));
        $this->apiJson('GET', '/api/v1/payment-links/'.$link->reference)->assertForbidden();
    }

    // ─── Pegado de la URL: lista blanca de dominios ───────────────────────────

    public function test_el_agente_pega_la_url_y_le_llega_al_cliente(): void
    {
        Notification::fake();
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);

        $this->adjuntar($link, $this->usuario('admin'))
            ->assertOk()
            ->assertJsonPath('data.attributes.status', PaymentLink::STATUS_ACTIVE)
            ->assertJsonPath('data.attributes.host', 'pagos.baccredomatic.com');

        Notification::assertSentTo($booking->user, PaymentLinkIssuedNotification::class);
        $this->assertNotNull($link->fresh()->sent_at);
    }

    public function test_no_se_acepta_una_url_fuera_de_la_lista_blanca(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));

        // Ese enlace se le reenvía al cliente con nuestra marca detrás: aceptar
        // cualquier dominio nos convierte en el aval de una web de phishing.
        $this->adjuntar($link, $this->usuario('admin'), 'https://bac-credomatic-pagos.example.com/l/1')
            ->assertStatus(422);

        $this->assertNull($link->fresh()->url);
    }

    public function test_no_se_acepta_una_url_con_credenciales_incrustadas(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));

        // `https://baccredomatic.com@malicioso.com/` apunta al host del final.
        $this->adjuntar($link, $this->usuario('admin'), 'https://baccredomatic.com@malicioso.example/l/1')
            ->assertStatus(422);
    }

    public function test_no_se_acepta_una_url_sin_https(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));

        $this->adjuntar($link, $this->usuario('admin'), 'http://pagos.baccredomatic.com/l/1')
            ->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_emitir_la_url(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));

        $this->adjuntar($link, $this->usuario('editor'))->assertForbidden();
    }

    // ─── Autorreporte del cliente (Fase 2) ───────────────────────────────────

    public function test_el_cliente_reporta_el_pago_con_comprobante(): void
    {
        Storage::fake('private');
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);
        $this->adjuntar($link, $this->usuario('admin'));

        Passport::actingAs($booking->user);
        $this->post('/api/v1/payment-links/'.$link->reference.'/report', [
            'authorization' => '445566',
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertOk()->assertJsonPath('data.attributes.status', PaymentLink::STATUS_REPORTED);

        $link->refresh();
        $this->assertSame('445566', $link->reported_ref);
        $this->assertTrue($link->hasMedia('payment_proof'));

        // Declarar un pago NO es cobrarlo: el dinero sigue sin estar confirmado.
        $this->assertSame('pending', $link->payment->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_el_comprobante_no_queda_en_disco_publico(): void
    {
        Storage::fake('private');
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);

        Passport::actingAs($booking->user);
        $this->post('/api/v1/payment-links/'.$link->reference.'/report', [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertOk();

        // Puede ser la foto de una tarjeta: nunca en una URL pública.
        $this->assertSame('private', $link->fresh()->getFirstMedia('payment_proof')->disk);
    }

    public function test_un_tercero_no_puede_reportar_el_pago_de_otro(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));

        Passport::actingAs($this->usuario('user'));
        $this->post('/api/v1/payment-links/'.$link->reference.'/report', ['authorization' => '1'])
            ->assertForbidden();
    }

    // ─── Confirmación: donde se mueve el dinero ──────────────────────────────

    public function test_el_agente_confirma_y_la_reserva_queda_pagada(): void
    {
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        // Unas horas antes: el cargo ocurrió mientras el enlace estaba vivo, que
        // es lo que la validación exige (no puede ser previo a su emisión).
        $fechaBanco = now()->subHours(3);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm',
            $this->payloadConfirmacion(['paid_at' => $fechaBanco->toDateTimeString(), 'note' => 'lote 0912'])
        )->assertOk()->assertJsonPath('data.attributes.status', PaymentLink::STATUS_CONFIRMED);

        $payment = $link->fresh()->payment;
        $this->assertSame('paid', $payment->status);
        $this->assertSame('778812', $payment->transaction_reference);
        $this->assertSame($agente->id, $payment->confirmed_by);
        $this->assertSame('lote 0912', $payment->confirmation_note);

        // La fecha del cobro es la del banco, no la de la confirmación: de ella
        // dependen el cuadre del mes y la fecha del DTE.
        $this->assertSame($fechaBanco->toDateTimeString(), $payment->paid_at->toDateTimeString());

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_la_misma_autorizacion_no_confirma_dos_reservas(): void
    {
        $agente = $this->usuario('admin');

        $primera = $this->reserva($this->usuario('user'));
        $linkA = $this->emitir($primera);
        $this->adjuntar($linkA, $agente);

        $segunda = $this->reserva($this->usuario('user'));
        $linkB = $this->emitir($segunda);
        $this->adjuntar($linkB, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$linkA->reference.'/confirm', $this->payloadConfirmacion())
            ->assertOk();

        // Confirmar la reserva B con el comprobante de la A es el error más
        // probable de un turno con prisa. Aquí se corta.
        $this->apiJson('POST', '/api/v1/payment-links/'.$linkB->reference.'/confirm', $this->payloadConfirmacion())
            ->assertStatus(422);

        $this->assertSame('pending', $linkB->fresh()->payment->status);
    }

    public function test_no_se_puede_confirmar_mas_de_lo_que_pedia_el_enlace(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm',
            $this->payloadConfirmacion(['charged' => 150])
        )->assertStatus(422);
    }

    public function test_un_cobro_parcial_deja_el_resto_pendiente(): void
    {
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm',
            $this->payloadConfirmacion(['charged' => 40])
        )->assertOk();

        $this->assertEquals(40.0, (float) $link->fresh()->payment->amount);
        // Cobrado a medias: la reserva NO se da por confirmada.
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
        $this->assertEquals(60.0, app(PaymentService::class)->outstandingFor($booking->fresh()));
    }

    public function test_confirmar_dos_veces_no_cobra_dos_veces(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())->assertOk();
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())->assertOk();

        $this->assertSame(1, Payment::where('status', 'paid')->count());
    }

    public function test_un_enlace_caducado_admite_un_cobro_tardio(): void
    {
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        // No podemos anular el enlace en el banco, así que un cliente puede pagar
        // uno que nosotros dimos por muerto. El dinero entra igual.
        $link->update(['expires_at' => now()->subDay(), 'status' => PaymentLink::STATUS_EXPIRED]);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())
            ->assertOk();

        $this->assertSame('paid', $link->fresh()->payment->status);
    }

    public function test_la_fecha_del_cobro_respeta_la_zona_horaria_que_envia_el_panel(): void
    {
        // El servidor corre en UTC y el negocio en America/El_Salvador (UTC-6).
        // El panel manda el instante en ISO con zona; guardarlo como si fuera
        // hora del servidor movía el cobro seis horas y descuadraba el mes.
        config(['app.timezone' => 'UTC']);

        $link = $this->emitir($this->reserva($this->usuario('user')));
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        $instante = now()->subHours(2);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm',
            $this->payloadConfirmacion(['paid_at' => $instante->copy()->setTimezone('America/El_Salvador')->toIso8601String()])
        )->assertOk();

        $this->assertSame(
            $instante->utc()->format('Y-m-d H:i'),
            $link->fresh()->payment->paid_at->utc()->format('Y-m-d H:i'),
        );
    }

    public function test_la_fecha_del_cobro_no_puede_estar_en_el_futuro(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm',
            $this->payloadConfirmacion(['paid_at' => now()->addDay()->toDateTimeString()])
        )->assertStatus(422);
    }

    public function test_sin_permiso_de_confirmacion_no_se_cobra(): void
    {
        $link = $this->emitir($this->reserva($this->usuario('user')));
        $this->adjuntar($link, $this->usuario('admin'));

        // 'editor' administra el catálogo pero no debe poder dar dinero por bueno.
        Passport::actingAs($this->usuario('editor'));
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())
            ->assertForbidden();

        $this->assertSame('pending', $link->fresh()->payment->status);
    }

    public function test_con_doble_control_quien_emite_no_confirma(): void
    {
        $this->habilitarEnlaces(['payment_bac_dual_control' => true]);

        $link = $this->emitir($this->reserva($this->usuario('user')));
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())
            ->assertStatus(422);

        // Otra persona sí puede.
        Passport::actingAs($this->usuario('admin'));
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())
            ->assertOk();
    }

    // ─── Reverso ──────────────────────────────────────────────────────────────

    public function test_anular_un_cobro_devuelve_el_saldo_sin_borrar_el_historial(): void
    {
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);
        $agente = $this->usuario('admin');
        $this->adjuntar($link, $agente);

        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', $this->payloadConfirmacion())->assertOk();

        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/void', [
            'data' => ['type' => 'payment-links', 'attributes' => ['reason' => 'contracargo del banco']],
        ])->assertOk();

        $payment = $link->fresh()->payment;
        // La fila se conserva por auditoría, pero ya no cuenta como dinero.
        $this->assertNotNull($payment->voided_at);
        $this->assertSame('contracargo del banco', $payment->void_reason);
        $this->assertSame(1, Payment::count());
        $this->assertEquals(100.0, app(PaymentService::class)->outstandingFor($booking->fresh()));
    }

    // ─── Integridad ───────────────────────────────────────────────────────────

    public function test_borrar_la_reserva_no_deja_enlaces_ni_comprobantes_huerfanos(): void
    {
        Storage::fake('private');
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->emitir($booking);

        Passport::actingAs($booking->user);
        $this->post('/api/v1/payment-links/'.$link->reference.'/report', [
            'proof' => UploadedFile::fake()->image('c.jpg'),
        ])->assertOk();

        $ruta = $link->fresh()->getFirstMedia('payment_proof')->getPathRelativeToRoot();

        $booking->delete();

        $this->assertSame(0, PaymentLink::count());
        $this->assertSame(0, Payment::count());
        Storage::disk('private')->assertMissing($ruta);
    }
}
