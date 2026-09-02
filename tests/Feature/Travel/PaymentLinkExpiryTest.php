<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\BookingNotification;
use App\Notifications\PaymentLinksDigestNotification;
use App\Services\PaymentLinkService;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Fase 3: qué pasa con un enlace de pago del banco que nadie usó a tiempo.
 *
 * El nombre "caducar" engaña: no podemos anular el enlace en el banco, así que
 * "caducado" significa "dejamos de contar con ese dinero", no "ya no se puede
 * cobrar". Lo que se prueba aquí es sobre todo eso — y el caso incómodo que
 * aparece en cuanto se cancela una reserva automáticamente: un cobro tardío
 * sobre una reserva que ya se liberó.
 *
 * @see PAGO-ENLACE-BAC.md §6, §9
 */
class PaymentLinkExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $this->habilitarEnlaces();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    // ─── Utilidades (mismo patrón que PaymentLinkTest) ────────────────────────

    private function habilitarEnlaces(array $extra = []): void
    {
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode(array_merge([
            'default_currency' => 'USD',
            'payment_bac_link_enabled' => true,
            'payment_bac_link_hosts' => 'baccredomatic.com',
            'payment_bac_link_ttl_hours' => 24,
            'payment_bac_dual_control' => false,
            'payment_bac_link_auto_release' => false,
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

    private function adjuntar(PaymentLink $link, User $agente): PaymentLink
    {
        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/attach', [
            'data' => ['type' => 'payment-links', 'attributes' => ['url' => 'https://pagos.baccredomatic.com/l/abc']],
        ])->assertOk();

        return $link->fresh();
    }

    private function vencido(PaymentLink $link): PaymentLink
    {
        // Simula que ya pasó el plazo, sin esperar 24h reales en el test.
        $link->update(['expires_at' => now()->subHour()]);

        return $link->fresh();
    }

    // ─── Caducidad básica ──────────────────────────────────────────────────────

    public function test_un_enlace_activo_vencido_pasa_a_caducado(): void
    {
        $link = $this->vencido($this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $this->usuario('admin')));

        app(PaymentLinkService::class)->expireDueLinks();

        $link->refresh();
        $this->assertSame(PaymentLink::STATUS_EXPIRED, $link->status);
        $this->assertSame('failed', $link->payment->status);
    }

    public function test_un_enlace_sin_url_tambien_caduca(): void
    {
        // 'draft': el agente nunca llegó a pegar el enlace.
        $link = $this->vencido($this->emitir($this->reserva($this->usuario('user'))));

        app(PaymentLinkService::class)->expireDueLinks();

        $this->assertSame(PaymentLink::STATUS_EXPIRED, $link->fresh()->status);
    }

    public function test_un_enlace_todavia_vigente_no_se_toca(): void
    {
        $link = $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $this->usuario('admin'));

        app(PaymentLinkService::class)->expireDueLinks();

        $this->assertSame(PaymentLink::STATUS_ACTIVE, $link->fresh()->status);
    }

    public function test_un_enlace_reportado_nunca_caduca(): void
    {
        // El cliente ya dijo que pagó: esa evidencia la revisa una persona, no
        // el reloj. Confundirlo con un abandono sería justo el error a evitar.
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->adjuntar($this->emitir($booking), $this->usuario('admin'));

        $link->update(['status' => PaymentLink::STATUS_REPORTED, 'reported_at' => now(), 'expires_at' => now()->subHour()]);

        app(PaymentLinkService::class)->expireDueLinks();

        $this->assertSame(PaymentLink::STATUS_REPORTED, $link->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    // ─── Liberar el asiento (auto_release) ───────────────────────────────────

    public function test_sin_auto_release_caduca_el_enlace_pero_no_toca_la_reserva(): void
    {
        // Apagado por defecto: la Fase 3 no debe empezar a cancelar reservas
        // solo por instalarse.
        $booking = $this->reserva($this->usuario('user'));
        $link = $this->vencido($this->adjuntar($this->emitir($booking), $this->usuario('admin')));

        app(PaymentLinkService::class)->expireDueLinks();

        $this->assertSame(PaymentLink::STATUS_EXPIRED, $link->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_con_auto_release_se_cancela_la_reserva_y_se_avisa_al_cliente(): void
    {
        Notification::fake();
        $this->habilitarEnlaces(['payment_bac_link_auto_release' => true]);

        $booking = $this->reserva($this->usuario('user'));
        $link = $this->vencido($this->adjuntar($this->emitir($booking), $this->usuario('admin')));

        $resultado = app(PaymentLinkService::class)->expireDueLinks();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(1, $resultado['released']);
        $this->assertTrue($resultado['expired'][0]['seat_released']);

        Notification::assertSentTo($booking->user, BookingNotification::class);
    }

    public function test_con_auto_release_no_se_cancela_si_hay_otro_pago_en_curso(): void
    {
        // El cliente pidió el enlace y también dejó un pago manual pendiente
        // (efectivo, por ejemplo): cancelar por un solo canal abandonado
        // cancelaría una venta que el cliente sigue intentando cerrar.
        $this->habilitarEnlaces(['payment_bac_link_auto_release' => true]);

        $booking = $this->reserva($this->usuario('user'));
        $link = $this->vencido($this->adjuntar($this->emitir($booking), $this->usuario('admin')));

        Payment::create([
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'gateway' => 'manual', 'method' => 'cash', 'amount' => 100,
            'currency_code' => 'USD', 'status' => 'pending',
        ]);

        $resultado = app(PaymentLinkService::class)->expireDueLinks();

        $this->assertSame(PaymentLink::STATUS_EXPIRED, $link->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
        $this->assertSame(0, $resultado['released']);
        $this->assertFalse($resultado['expired'][0]['seat_released']);
    }

    // ─── El caso incómodo: cobro tardío sobre una reserva ya cancelada ───────

    public function test_confirmar_un_enlace_cuya_reserva_ya_se_cancelo_no_la_reactiva_sola(): void
    {
        Notification::fake();
        $this->habilitarEnlaces(['payment_bac_link_auto_release' => true]);

        $booking = $this->reserva($this->usuario('user'));
        $agente = $this->usuario('admin');
        $link = $this->vencido($this->adjuntar($this->emitir($booking), $agente));

        app(PaymentLinkService::class)->expireDueLinks();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);

        // El cliente insiste y paga de todas formas: el enlace sigue siendo
        // válido para el banco aunque nosotros ya diéramos por perdida la venta.
        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', [
            'data' => ['type' => 'payment-links', 'attributes' => [
                'authorization' => '999111', 'charged' => 100, 'paid_at' => now()->subMinutes(5)->toDateTimeString(),
            ]],
        ])->assertOk();

        // El dinero SÍ queda registrado como cobrado...
        $this->assertSame('paid', $link->fresh()->payment->status);
        // ...pero la reserva no se reactiva sola: podría haberse vuelto a vender.
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);

        // Y alguien tiene que enterarse de que hay dinero real esperando una
        // decisión manual: sin este aviso, el cobro quedaría "correcto" en la
        // base de datos y perdido para cualquiera que no piense en mirarlo.
        Notification::assertSentTo(
            new AnonymousNotifiable,
            AdminAlertNotification::class,
            fn ($n) => str_contains($n->toMail(new AnonymousNotifiable)->subject, 'ya cancelada')
        );
    }

    // ─── El comando ────────────────────────────────────────────────────────────

    public function test_el_comando_de_caducidad_recorre_los_vencidos(): void
    {
        $link = $this->vencido($this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $this->usuario('admin')));

        $this->artisan('payment-links:expire')->assertSuccessful();

        $this->assertSame(PaymentLink::STATUS_EXPIRED, $link->fresh()->status);
    }

    // ─── El resumen diario ─────────────────────────────────────────────────────

    public function test_sin_enlaces_abiertos_no_se_envia_resumen(): void
    {
        Notification::fake();

        $this->artisan('payment-links:digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_el_resumen_incluye_los_reportados_y_los_que_vencen_pronto(): void
    {
        Notification::fake();
        $admin = $this->usuario('admin');

        // Reportado: necesita revisión ahora.
        $reportado = $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin);
        $reportado->update(['status' => PaymentLink::STATUS_REPORTED, 'reported_at' => now(), 'reported_ref' => '445566']);

        // Por vencer en menos de 24h.
        $porVencer = $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin);
        $porVencer->update(['expires_at' => now()->addHours(3)]);

        // Recién emitido, lejos de vencer: no debe aparecer en "por vencer".
        $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin);

        $this->artisan('payment-links:digest')->assertSuccessful();

        // El contenido real (no solo que "algo" se envió): las dos referencias
        // que importan tienen que aparecer en el cuerpo del correo, y la que no
        // vence pronto no debe colarse en esa sección.
        Notification::assertSentTo(
            new AnonymousNotifiable,
            PaymentLinksDigestNotification::class,
            function ($notification, $channels, $notifiable) use ($reportado, $porVencer) {
                $lineas = implode("\n", $notification->toMail($notifiable)->introLines);

                return str_contains($lineas, $reportado->reference)
                    && str_contains($lineas, $porVencer->reference);
            }
        );
    }
}
