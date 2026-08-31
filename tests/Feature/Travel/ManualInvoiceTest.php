<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Support\InvoiceDocument;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Facturación manual desde el back-office y diseños seleccionables del PDF.
 */
class ManualInvoiceTest extends TestCase
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
            'username' => 'facturador'.uniqid(),
            'first_name' => 'Admin', 'last_name' => 'Facturas',
            'email' => uniqid('fact').'@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function tour(float $precio = 65): Tour
    {
        return Tour::create([
            'title' => 'Volcán Santa Ana', 'description' => 'd', 'price' => $precio,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function vehiculo(float $tarifa = 45): TransportVehicle
    {
        return TransportVehicle::create([
            'title' => 'Toyota Hilux', 'description' => 'd', 'vehicle_type' => 'pickup',
            'daily_rate' => $tarifa, 'hourly_rate' => 10, 'capacity' => 5,
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

    private function payload(array $items, array $extra = []): array
    {
        return ['data' => ['type' => 'invoices', 'attributes' => array_merge([
            'receptor_name' => 'María González',
            'receptor_document' => '0614-010190-101-2',
            'receptor_email' => 'maria@example.com',
            'items' => $items,
        ], $extra)]];
    }

    // ─── Creación manual ──────────────────────────────────────────────────────

    public function test_un_admin_emite_una_factura_con_tour_y_vehiculo(): void
    {
        $this->admin();
        $tour = $this->tour(65);
        $vehiculo = $this->vehiculo(45);

        $respuesta = $this->apiJson('POST', '/api/v1/invoices', $this->payload([
            ['tour_id' => $tour->id, 'quantity' => 2, 'unit_price' => 65],
            ['transport_vehicle_id' => $vehiculo->id, 'quantity' => 3, 'unit_price' => 45],
            ['description' => 'Guía privado', 'quantity' => 1, 'unit_price' => 40],
        ]))->assertCreated();

        $attrs = $respuesta->json('data.attributes');

        // 65x2 + 45x3 + 40 = 305
        $this->assertEquals('305.00', $attrs['amount']);
        $this->assertTrue($attrs['is_manual']);
        $this->assertCount(3, $attrs['items']);
        // La descripción se toma del catálogo cuando no se envía.
        // El orden del payload se respeta, incluso mezclando líneas de catálogo
        // con líneas libres (que no traen las mismas claves).
        $this->assertSame('Volcán Santa Ana', $attrs['items'][0]['description']);
        $this->assertSame('Toyota Hilux', $attrs['items'][1]['description']);
        $this->assertSame('Guía privado', $attrs['items'][2]['description']);
    }

    public function test_el_importe_de_la_factura_no_lo_decide_el_cliente(): void
    {
        $this->admin();
        $tour = $this->tour(65);

        // Aunque se intente colar un total propio, se recalcula desde las líneas.
        $this->apiJson('POST', '/api/v1/invoices', $this->payload(
            [['tour_id' => $tour->id, 'quantity' => 2, 'unit_price' => 65]],
            ['amount' => 1, 'total' => 1],
        ))->assertCreated();

        $this->assertEquals('130.00', Invoice::first()->amount);
    }

    public function test_una_factura_manual_necesita_al_menos_un_concepto(): void
    {
        $this->admin();

        $this->apiJson('POST', '/api/v1/invoices', $this->payload([]))->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_emitir(): void
    {
        $user = User::create([
            'username' => 'basico', 'first_name' => 'B', 'last_name' => 'C',
            'email' => 'basico@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('user');
        Passport::actingAs($user->fresh());

        $this->apiJson('POST', '/api/v1/invoices', $this->payload([
            ['description' => 'Algo', 'quantity' => 1, 'unit_price' => 10],
        ]))->assertForbidden();
    }

    public function test_editar_los_conceptos_recalcula_el_total(): void
    {
        $this->admin();
        $tour = $this->tour(65);

        $id = $this->apiJson('POST', '/api/v1/invoices', $this->payload([
            ['tour_id' => $tour->id, 'quantity' => 1, 'unit_price' => 65],
        ]))->json('data.id');

        $this->apiJson('PATCH', '/api/v1/invoices/'.$id, [
            'data' => ['id' => (string) $id, 'type' => 'invoices', 'attributes' => [
                'items' => [['description' => 'Paquete completo', 'quantity' => 4, 'unit_price' => 50]],
            ]],
        ])->assertOk();

        $this->assertEquals('200.00', Invoice::find($id)->amount);
        $this->assertCount(1, Invoice::find($id)->items);
    }

    public function test_un_patch_sin_conceptos_no_borra_los_existentes(): void
    {
        $this->admin();
        $tour = $this->tour(65);

        $id = $this->apiJson('POST', '/api/v1/invoices', $this->payload([
            ['tour_id' => $tour->id, 'quantity' => 1, 'unit_price' => 65],
        ]))->json('data.id');

        $this->apiJson('PATCH', '/api/v1/invoices/'.$id, [
            'data' => ['id' => (string) $id, 'type' => 'invoices', 'attributes' => ['receptor_name' => 'Otro Nombre']],
        ])->assertOk();

        $this->assertCount(1, Invoice::find($id)->items);
        $this->assertEquals('65.00', Invoice::find($id)->amount);
    }

    // ─── Borrado ──────────────────────────────────────────────────────────────

    public function test_no_se_borra_una_factura_ligada_a_una_reserva(): void
    {
        $admin = $this->admin();
        $tour = $this->tour();
        $booking = Booking::create([
            'user_id' => $admin->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(5), 'party_size' => 1, 'total_price' => 65,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);
        $factura = Invoice::create([
            'booking_id' => $booking->id, 'amount' => 65, 'currency_code' => 'USD',
            'status' => 'pending', 'dte_status' => Invoice::DTE_NOT_GENERATED, 'issued_at' => now(),
        ]);

        $this->apiJson('DELETE', '/api/v1/invoices/'.$factura->id)->assertStatus(409);
        $this->assertDatabaseHas('invoices', ['id' => $factura->id, 'deleted_at' => null]);
    }

    public function test_se_borra_una_factura_manual_sin_dte(): void
    {
        $this->admin();

        $id = $this->apiJson('POST', '/api/v1/invoices', $this->payload([
            ['description' => 'Servicio suelto', 'quantity' => 1, 'unit_price' => 25],
        ]))->json('data.id');

        $this->apiJson('DELETE', '/api/v1/invoices/'.$id)->assertNoContent();

        // Invoice usa soft delete: la fila se marca borrada y sus conceptos se
        // conservan, de modo que restaurarla la devuelve completa.
        $this->assertSoftDeleted('invoices', ['id' => $id]);
        $this->assertSame(1, InvoiceItem::where('invoice_id', $id)->count());
    }

    // ─── Diseños del PDF ──────────────────────────────────────────────────────

    private function facturaConConceptos(): Invoice
    {
        $tour = $this->tour(65);

        return Invoice::find($this->apiJson('POST', '/api/v1/invoices', $this->payload([
            ['tour_id' => $tour->id, 'quantity' => 2, 'unit_price' => 65],
            ['description' => 'Transporte', 'quantity' => 1, 'unit_price' => 30],
        ]))->json('data.id'));
    }

    public function test_los_tres_disenos_generan_un_pdf_valido(): void
    {
        $this->admin();
        $factura = $this->facturaConConceptos();

        foreach (array_keys(InvoiceDocument::TEMPLATES) as $plantilla) {
            $pdf = InvoiceDocument::pdf($factura, $plantilla);

            $this->assertStringStartsWith('%PDF', $pdf, "El diseño {$plantilla} no produjo un PDF.");
            $this->assertGreaterThan(1000, strlen($pdf), "El diseño {$plantilla} salió vacío.");
        }
    }

    public function test_el_diseno_predeterminado_sale_de_los_ajustes(): void
    {
        $this->admin();

        $this->assertSame('clasica', InvoiceDocument::template());

        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode(['invoice_template' => 'minimal'])]);
        SiteSettings::flush();

        $this->assertSame('minimal', InvoiceDocument::template());
    }

    public function test_un_diseno_desconocido_cae_al_predeterminado(): void
    {
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode(['invoice_template' => 'inexistente'])]);
        SiteSettings::flush();

        $this->assertSame(InvoiceDocument::DEFAULT_TEMPLATE, InvoiceDocument::template());
    }

    public function test_se_descarga_el_pdf_y_se_puede_previsualizar_otro_diseno(): void
    {
        $this->admin();
        $factura = $this->facturaConConceptos();

        $this->get('/api/v1/invoices/'.$factura->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get('/api/v1/invoices/'.$factura->id.'/pdf?template=moderna')->assertOk();
        // Una plantilla inventada no debe llegar al renderizador.
        $this->getJson('/api/v1/invoices/'.$factura->id.'/pdf?template=fantasia')->assertStatus(422);
    }

    public function test_el_pdf_de_una_factura_de_reserva_no_sale_vacio(): void
    {
        $admin = $this->admin();
        $tour = $this->tour();
        $booking = Booking::create([
            'user_id' => $admin->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(5), 'party_size' => 2, 'total_price' => 130,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);
        $factura = Invoice::create([
            'booking_id' => $booking->id, 'amount' => 130, 'currency_code' => 'USD',
            'status' => 'pending', 'dte_status' => Invoice::DTE_NOT_GENERATED, 'issued_at' => now(),
        ]);

        // No tiene líneas propias: el documento usa la reserva como concepto.
        $datos = InvoiceDocument::data($factura->fresh());

        $this->assertCount(1, $datos['conceptos']);
        $this->assertSame(130.0, $datos['total']);
        $this->assertStringContainsString('Volcán', $datos['conceptos'][0]['descripcion']);
    }
}
