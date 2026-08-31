<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Currency;
use App\Models\ProductReview;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Endpoints que el panel ya invocaba pero que no existían: borrado de tours y
 * vehículos, y edición de monedas. Más la validación de los ajustes del sitio.
 */
class CatalogDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        SiteSettings::flush();
    }

    private function admin(): User
    {
        $user = User::create([
            'username' => 'adm'.uniqid(), 'first_name' => 'Admin', 'last_name' => 'Cat',
            'email' => uniqid('adm').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function cliente(): User
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'Cli', 'last_name' => 'Ente',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'Ruta de las Flores', 'description' => 'd', 'price' => 45,
            'max_capacity' => 10, 'location' => 'Ahuachapán', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function vehiculo(): TransportVehicle
    {
        return TransportVehicle::create([
            'title' => 'Van Mercedes', 'description' => 'd', 'vehicle_type' => 'van',
            'daily_rate' => 90, 'hourly_rate' => 15, 'capacity' => 12,
            'location' => 'San Salvador', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── Borrado de tours ─────────────────────────────────────────────────────

    public function test_un_admin_borra_un_tour_sin_reservas(): void
    {
        $this->admin();
        $tour = $this->tour();

        $this->apiJson('DELETE', '/api/v1/tours/'.$tour->id)->assertNoContent();

        $this->assertDatabaseMissing('tours', ['id' => $tour->id]);
    }

    public function test_borrar_un_tour_con_reservas_responde_409_y_no_lo_borra(): void
    {
        $this->admin();
        $tour = $this->tour();
        Booking::create([
            'user_id' => $this->cliente()->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(5), 'party_size' => 2, 'total_price' => 90,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        // La guarda vive en BookableObserver; el controlador la traduce a 409 en
        // vez de dejar escapar la excepción como un error 500.
        $this->apiJson('DELETE', '/api/v1/tours/'.$tour->id)
            ->assertStatus(409)
            ->assertJsonPath('errors.0.title', 'tour.hasBookings');

        $this->assertDatabaseHas('tours', ['id' => $tour->id]);
        $this->assertSame(1, Booking::count());
    }

    public function test_borrar_un_tour_arrastra_sus_resenas(): void
    {
        $this->admin();
        $tour = $this->tour();
        ProductReview::create([
            'user_id' => $this->cliente()->id, 'reviewable_type' => Tour::class,
            'reviewable_id' => $tour->id, 'rating' => 5, 'is_approved' => true,
        ]);

        $this->apiJson('DELETE', '/api/v1/tours/'.$tour->id)->assertNoContent();

        $this->assertSame(0, ProductReview::count());
    }

    public function test_sin_permiso_no_se_borra_un_tour(): void
    {
        Passport::actingAs($this->cliente());
        $tour = $this->tour();

        $this->apiJson('DELETE', '/api/v1/tours/'.$tour->id)->assertForbidden();
        $this->assertDatabaseHas('tours', ['id' => $tour->id]);
    }

    // ─── Borrado de vehículos ─────────────────────────────────────────────────

    public function test_un_admin_borra_un_vehiculo_sin_reservas(): void
    {
        $this->admin();
        $vehiculo = $this->vehiculo();

        $this->apiJson('DELETE', '/api/v1/transport-vehicles/'.$vehiculo->id)->assertNoContent();

        $this->assertDatabaseMissing('transport_vehicles', ['id' => $vehiculo->id]);
    }

    public function test_borrar_un_vehiculo_con_reservas_responde_409(): void
    {
        $this->admin();
        $vehiculo = $this->vehiculo();
        Booking::create([
            'user_id' => $this->cliente()->id, 'bookable_type' => TransportVehicle::class,
            'bookable_id' => $vehiculo->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(5),
            'party_size' => 1, 'total_price' => 180, 'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        $this->apiJson('DELETE', '/api/v1/transport-vehicles/'.$vehiculo->id)
            ->assertStatus(409)
            ->assertJsonPath('errors.0.title', 'vehicle.hasBookings');

        $this->assertDatabaseHas('transport_vehicles', ['id' => $vehiculo->id]);
    }

    // ─── Edición de monedas ───────────────────────────────────────────────────

    private function moneda(array $extra = []): Currency
    {
        return Currency::create(array_merge([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ], $extra));
    }

    private function payloadMoneda(Currency $moneda, array $attrs): array
    {
        // El middleware JSON:API exige `data.id` en PATCH, igual que lo envía el panel.
        return ['data' => ['id' => (string) $moneda->id, 'type' => 'currencies', 'attributes' => array_merge([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.92,
        ], $attrs)]];
    }

    public function test_un_admin_edita_una_moneda(): void
    {
        $this->admin();
        $moneda = $this->moneda(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.9, 'is_default' => false]);

        $this->apiJson('PATCH', '/api/v1/currencies/'.$moneda->id, $this->payloadMoneda($moneda, [
            'name' => 'Euro europeo', 'rate_to_usd' => 0.95,
        ]))->assertOk();

        $moneda->refresh();
        $this->assertSame('Euro europeo', $moneda->name);
        // rate_to_usd se castea con 8 decimales.
        $this->assertEquals(0.95, (float) $moneda->rate_to_usd);
    }

    public function test_editar_una_moneda_no_choca_con_su_propio_codigo(): void
    {
        $this->admin();
        $moneda = $this->moneda(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.9, 'is_default' => false]);

        // Reenviar el mismo código no debe dar error de unicidad.
        $this->apiJson('PATCH', '/api/v1/currencies/'.$moneda->id, $this->payloadMoneda($moneda, [
            'code' => 'EUR', 'name' => 'Euro',
        ]))->assertOk();
    }

    public function test_no_se_duplica_el_codigo_de_otra_moneda(): void
    {
        $this->admin();
        $this->moneda(); // USD
        $euro = $this->moneda(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.9, 'is_default' => false]);

        $this->apiJson('PATCH', '/api/v1/currencies/'.$euro->id, $this->payloadMoneda($euro, ['code' => 'USD']))
            ->assertStatus(422);
    }

    public function test_solo_queda_una_moneda_por_defecto(): void
    {
        $this->admin();
        $usd = $this->moneda(); // is_default = true
        $euro = $this->moneda(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.9, 'is_default' => false]);

        $this->apiJson('PATCH', '/api/v1/currencies/'.$euro->id, $this->payloadMoneda($euro, ['is_default' => true]))
            ->assertOk();

        $this->assertTrue((bool) $euro->fresh()->is_default);
        $this->assertFalse((bool) $usd->fresh()->is_default);
    }

    // ─── Validación de ajustes ────────────────────────────────────────────────

    private function guardarAjustes(array $attrs): TestResponse
    {
        return $this->apiJson('PATCH', '/api/v1/settings', [
            'data' => ['type' => 'settings', 'id' => 'current', 'attributes' => $attrs],
        ]);
    }

    public function test_guardar_ajustes_validos_sigue_funcionando(): void
    {
        $this->admin();

        $this->guardarAjustes([
            'app_name' => 'Cusgo Adventures',
            'contact_email' => 'hola@cusgoadventures.com',
            'booking_cancellation_hours' => 24,
            'booking_min_advance_days' => 1,
            'timezone' => 'America/El_Salvador',
            'payment_whatsapp_enabled' => true,
        ])->assertOk();

        $app = json_decode(Setting::where('key', 'app')->value('value'), true);
        $this->assertSame('Cusgo Adventures', $app['app_name']);
        $this->assertSame(24, $app['booking_cancellation_hours']);
    }

    public function test_una_hora_de_cancelacion_no_numerica_se_rechaza(): void
    {
        $this->admin();

        // Antes se guardaba tal cual y se casteaba a 0 al leerla: la ventana de
        // cancelación desaparecía sin que nadie se enterase.
        $this->guardarAjustes(['booking_cancellation_hours' => 'mañana'])->assertStatus(422);

        $this->assertSame(0, Setting::where('key', 'app')->count());
    }

    public function test_se_rechazan_valores_negativos_y_correos_invalidos(): void
    {
        $this->admin();

        $this->guardarAjustes(['max_daily_bookings' => -5])->assertStatus(422);
        $this->guardarAjustes(['contact_email' => 'no-es-un-correo'])->assertStatus(422);
        $this->guardarAjustes(['timezone' => 'Marte/Olympus'])->assertStatus(422);
        $this->guardarAjustes(['invoice_template' => 'inventada'])->assertStatus(422);
        $this->guardarAjustes(['default_currency' => 'DOLARES'])->assertStatus(422);
    }

    public function test_un_guardado_parcial_no_exige_el_resto_de_campos(): void
    {
        $this->admin();

        // El panel envía solo la pestaña que se está guardando.
        $this->guardarAjustes(['app_tagline' => 'Aventura auténtica'])->assertOk();
        $this->guardarAjustes(['payment_cash_enabled' => false])->assertOk();
    }
}
