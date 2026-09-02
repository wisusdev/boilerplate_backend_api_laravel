<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Conciliación contra el extracto del banco (Fase 3).
 *
 * El caso que de verdad importa no es "el extracto cuadra": es el que se
 * cuela en silencio si nadie mira — dinero que SÍ entró al banco y que en
 * nuestro sistema sigue como si nada hubiera pasado. Eso es lo que este
 * informe tiene que sacar a la luz, y de forma que no mueva un centavo por sí
 * mismo (confirmar sigue siendo un acto humano, uno por uno).
 *
 * @see PAGO-ENLACE-BAC.md §4.D.3
 */
class PaymentReconciliationTest extends TestCase
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

    private function habilitarEnlaces(): void
    {
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode([
            'default_currency' => 'USD',
            'payment_bac_link_enabled' => true,
            'payment_bac_link_hosts' => 'baccredomatic.com',
            'payment_bac_link_ttl_hours' => 24,
        ])]);
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
            'title' => 'Ruta de las Flores', 'description' => 'd', 'price' => 65,
            'max_capacity' => 10, 'location' => 'Ahuachapán', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function reserva(User $cliente, float $total = 65): Booking
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

    private function confirmar(PaymentLink $link, User $agente, string $autorizacion = '778812'): PaymentLink
    {
        Passport::actingAs($agente);
        $this->apiJson('POST', '/api/v1/payment-links/'.$link->reference.'/confirm', [
            'data' => ['type' => 'payment-links', 'attributes' => [
                'authorization' => $autorizacion, 'charged' => (float) $link->amount,
                'paid_at' => now()->subHour()->toDateTimeString(),
            ]],
        ])->assertOk();

        return $link->fresh();
    }

    private function subirExtracto(User $agente, string $csv): TestResponse
    {
        Passport::actingAs($agente);
        $archivo = UploadedFile::fake()->createWithContent('extracto.csv', $csv);

        return $this->post('/api/v1/payment-links/reconcile', ['statement' => $archivo]);
    }

    // ─── El caso que importa: dinero en el banco que no confirmamos ──────────

    public function test_detecta_un_enlace_cobrado_en_el_banco_y_no_confirmado_aqui(): void
    {
        $link = $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $this->usuario('admin'));

        $csv = "Fecha,Descripcion,Importe\n15/06/2026,pago {$link->reference},65.00\n";

        $res = $this->subirExtracto($this->usuario('admin'), $csv)->assertOk();

        $res->assertJsonCount(1, 'data.attributes.charged_not_confirmed');
        $res->assertJsonPath('data.attributes.charged_not_confirmed.0.reference', $link->reference);
        $res->assertJsonPath('data.attributes.confirmed_not_in_statement', []);
    }

    public function test_un_enlace_ya_confirmado_no_aparece_como_pendiente(): void
    {
        $admin = $this->usuario('admin');
        $link = $this->confirmar($this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin), $admin);

        $csv = "Fecha,Descripcion,Importe\n15/06/2026,pago {$link->reference},65.00\n";

        $res = $this->subirExtracto($admin, $csv)->assertOk();

        $res->assertJsonPath('data.attributes.charged_not_confirmed', []);
    }

    // ─── El otro lado: confirmamos algo que el extracto no respalda ──────────

    public function test_detecta_un_pago_confirmado_que_no_aparece_en_el_extracto(): void
    {
        $admin = $this->usuario('admin');
        $link = $this->confirmar($this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin), $admin);

        // Extracto del mismo día, pero sin ninguna fila para este enlace.
        $csv = "Fecha,Descripcion,Importe\n15/06/2026,otra cosa,10.00\n";

        $res = $this->subirExtracto($admin, $csv)->assertOk();

        $res->assertJsonCount(1, 'data.attributes.confirmed_not_in_statement');
        $res->assertJsonPath('data.attributes.confirmed_not_in_statement.0.reference', $link->reference);
    }

    public function test_un_pago_confirmado_fuera_del_rango_del_extracto_no_se_marca_ausente(): void
    {
        // El extracto de un día concreto no puede señalar como "ausente" un
        // cobro de dos semanas atrás: sencillamente no estaba en su alcance.
        $admin = $this->usuario('admin');
        $this->confirmar($this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin), $admin);

        $csv = "Fecha,Descripcion,Importe\n01/01/2026,algo,10.00\n";

        $res = $this->subirExtracto($admin, $csv)->assertOk();

        $res->assertJsonPath('data.attributes.confirmed_not_in_statement', []);
    }

    // ─── Respaldo por importe+fecha cuando el banco recorta la referencia ────

    public function test_si_el_banco_no_conserva_la_referencia_completa_empareja_por_importe_y_fecha(): void
    {
        $link = $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $this->usuario('admin'));

        $csv = "Fecha,Descripcion,Importe\n15/06/2026,COMPRA POS,65.00\n";

        $res = $this->subirExtracto($this->usuario('admin'), $csv)->assertOk();

        $res->assertJsonPath('data.attributes.charged_not_confirmed.0.reference', $link->reference);
    }

    public function test_dos_enlaces_del_mismo_importe_y_fecha_quedan_como_ambiguos_no_se_adivina(): void
    {
        $admin = $this->usuario('admin');
        $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin);
        $this->adjuntar($this->emitir($this->reserva($this->usuario('user'))), $admin);

        $csv = "Fecha,Descripcion,Importe\n15/06/2026,COMPRA POS,65.00\n";

        $res = $this->subirExtracto($admin, $csv)->assertOk();

        $res->assertJsonCount(1, 'data.attributes.ambiguous_rows');
        $res->assertJsonCount(0, 'data.attributes.charged_not_confirmed');
    }

    // ─── Ruido informativo, acotado ───────────────────────────────────────────

    public function test_filas_sin_relacion_con_ningun_enlace_se_listan_como_informativas(): void
    {
        $csv = "Fecha,Descripcion,Importe\n15/06/2026,cargo de otro comercio,12.00\n";

        $res = $this->subirExtracto($this->usuario('admin'), $csv)->assertOk();

        $res->assertJsonCount(1, 'data.attributes.unmatched_rows');
        $res->assertJsonPath('data.attributes.unmatched_rows_total', 1);
    }

    // ─── Formato del archivo ───────────────────────────────────────────────────

    public function test_un_csv_ilegible_responde_422_con_un_mensaje_claro(): void
    {
        $csv = "Columna1,Columna2\nx,y\n";

        $this->subirExtracto($this->usuario('admin'), $csv)->assertStatus(422);
    }

    // ─── Permisos: es información de cobros, no cualquiera la sube ──────────

    public function test_sin_permiso_de_confirmacion_no_se_puede_conciliar(): void
    {
        $this->subirExtracto($this->usuario('editor'), "Fecha,Importe\n15/06/2026,10.00\n")
            ->assertForbidden();
    }

    public function test_finanzas_puede_conciliar(): void
    {
        $this->subirExtracto($this->usuario('finanzas'), "Fecha,Importe\n15/06/2026,10.00\n")
            ->assertOk();
    }
}
