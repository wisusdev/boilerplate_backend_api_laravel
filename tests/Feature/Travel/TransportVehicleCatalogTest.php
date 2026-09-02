<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\TransportVehicle;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Catálogo de vehículos de renta: es producto vendible (con disponibilidad y
 * media) y no tenía ni una sola prueba. `CatalogDeletionTest` ya cubre el
 * borrado con la guarda de reservas activas; aquí va el resto.
 */
class TransportVehicleCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function admin(): User
    {
        $user = User::create([
            'username' => 'adm'.uniqid(), 'first_name' => 'Admin', 'last_name' => 'V',
            'email' => uniqid('adm').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function editor(): User
    {
        $user = User::create([
            'username' => 'ed'.uniqid(), 'first_name' => 'Editor', 'last_name' => 'V',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('editor');

        return $user;
    }

    private function vehiculo(array $extra = []): TransportVehicle
    {
        return TransportVehicle::create(array_merge([
            'title' => 'Van Mercedes', 'description' => 'd', 'vehicle_type' => 'van',
            'daily_rate' => 90, 'hourly_rate' => 15, 'capacity' => 12,
            'location' => 'San Salvador', 'currency_code' => 'USD', 'is_active' => true,
        ], $extra));
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function vehiculoPayload(array $attrs = []): array
    {
        return ['data' => ['type' => 'transport_vehicles', 'attributes' => array_merge([
            'title' => 'Microbús Hyundai', 'vehicle_type' => 'microbus',
            'location' => 'Santa Ana', 'capacity' => 20, 'daily_rate' => 120,
        ], $attrs)]];
    }

    // ─── Catálogo público ──────────────────────────────────────────────────────

    public function test_index_solo_lista_vehiculos_activos(): void
    {
        $this->vehiculo(['title' => 'Activo', 'is_active' => true]);
        $this->vehiculo(['title' => 'Inactivo', 'is_active' => false]);

        $data = $this->apiJson('GET', '/api/v1/transport-vehicles')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Activo', $data[0]['attributes']['title']);
    }

    public function test_show_devuelve_un_vehiculo(): void
    {
        $vehiculo = $this->vehiculo();

        $this->apiJson('GET', '/api/v1/transport-vehicles/'.$vehiculo->id)
            ->assertOk()
            ->assertJsonPath('data.attributes.title', 'Van Mercedes');
    }

    public function test_types_solo_devuelve_tipos_de_vehiculos_activos(): void
    {
        $this->vehiculo(['vehicle_type' => 'van', 'is_active' => true]);
        $this->vehiculo(['vehicle_type' => 'suv', 'is_active' => false]);

        $tipos = $this->apiJson('GET', '/api/v1/transport-vehicles/types')->assertOk()->json('data.attributes.types');

        $this->assertContains('van', $tipos);
        $this->assertNotContains('suv', $tipos);
    }

    // ─── Disponibilidad ────────────────────────────────────────────────────────

    public function test_availability_es_false_si_hay_una_reserva_solapada(): void
    {
        $vehiculo = $this->vehiculo();
        $cliente = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Booking::create([
            'user_id' => $cliente->id, 'bookable_type' => TransportVehicle::class, 'bookable_id' => $vehiculo->id,
            'starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-12 08:00:00',
            'party_size' => 1, 'total_price' => 180, 'currency_code' => 'USD', 'status' => Booking::STATUS_CONFIRMED,
        ]);

        $res = $this->apiJson('GET', '/api/v1/transport-vehicles/'.$vehiculo->id.'/availability?'.http_build_query([
            'pickup_at' => '2026-11-11 08:00:00', 'dropoff_at' => '2026-11-13 08:00:00',
        ]))->assertOk();

        $this->assertFalse($res->json('data.attributes.available'));
    }

    public function test_availability_es_true_fuera_del_rango_reservado(): void
    {
        $vehiculo = $this->vehiculo();
        $cliente = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Booking::create([
            'user_id' => $cliente->id, 'bookable_type' => TransportVehicle::class, 'bookable_id' => $vehiculo->id,
            'starts_at' => '2026-11-10 08:00:00', 'ends_at' => '2026-11-12 08:00:00',
            'party_size' => 1, 'total_price' => 180, 'currency_code' => 'USD', 'status' => Booking::STATUS_CONFIRMED,
        ]);

        $res = $this->apiJson('GET', '/api/v1/transport-vehicles/'.$vehiculo->id.'/availability?'.http_build_query([
            'pickup_at' => '2026-11-20 08:00:00', 'dropoff_at' => '2026-11-21 08:00:00',
        ]))->assertOk();

        $this->assertTrue($res->json('data.attributes.available'));
    }

    public function test_availability_exige_fechas_validas(): void
    {
        $vehiculo = $this->vehiculo();

        $this->apiJson('GET', '/api/v1/transport-vehicles/'.$vehiculo->id.'/availability?pickup_at=2026-11-10')
            ->assertStatus(422);
    }

    // ─── Alta y edición ─────────────────────────────────────────────────────────

    public function test_un_editor_crea_un_vehiculo(): void
    {
        Passport::actingAs($this->editor());

        $this->apiJson('POST', '/api/v1/transport-vehicles', $this->vehiculoPayload())
            ->assertCreated()
            ->assertJsonPath('data.attributes.title', 'Microbús Hyundai');

        $this->assertDatabaseHas('transport_vehicles', ['title' => 'Microbús Hyundai']);
    }

    public function test_un_usuario_sin_permiso_no_puede_crear_un_vehiculo(): void
    {
        $cliente = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($cliente);

        $this->apiJson('POST', '/api/v1/transport-vehicles', $this->vehiculoPayload())->assertForbidden();
        $this->assertDatabaseMissing('transport_vehicles', ['title' => 'Microbús Hyundai']);
    }

    public function test_crear_un_vehiculo_exige_los_campos_obligatorios(): void
    {
        Passport::actingAs($this->editor());

        $this->apiJson('POST', '/api/v1/transport-vehicles', [
            'data' => ['type' => 'transport_vehicles', 'attributes' => ['vehicle_type' => 'van']],
        ])->assertStatus(422);
    }

    public function test_un_editor_actualiza_un_vehiculo(): void
    {
        Passport::actingAs($this->editor());
        $vehiculo = $this->vehiculo();

        // El middleware JSON:API exige `data.id` en PATCH, igual que en el resto
        // de módulos (ver CatalogDeletionTest para monedas).
        $this->apiJson('PATCH', '/api/v1/transport-vehicles/'.$vehiculo->id, [
            'data' => ['id' => (string) $vehiculo->id, 'type' => 'transport_vehicles', 'attributes' => ['title' => 'Van Mercedes Sprinter']],
        ])->assertOk()->assertJsonPath('data.attributes.title', 'Van Mercedes Sprinter');
    }

    public function test_la_moneda_del_vehiculo_es_la_global_del_sitio_no_la_enviada(): void
    {
        Passport::actingAs($this->editor());

        $this->apiJson('POST', '/api/v1/transport-vehicles', $this->vehiculoPayload(['currency_code' => 'EUR']))
            ->assertCreated();

        // 'currency_code' no es un campo aceptado por TransportVehicleRequest:
        // el control lo tiene SiteSettings::currency(), no lo que mande el cliente.
        $this->assertDatabaseHas('transport_vehicles', ['title' => 'Microbús Hyundai', 'currency_code' => 'USD']);
    }

    // ─── Media ────────────────────────────────────────────────────────────────

    public function test_un_editor_sube_la_imagen_destacada(): void
    {
        Passport::actingAs($this->editor());
        $vehiculo = $this->vehiculo();

        $this->post('/api/v1/transport-vehicles/'.$vehiculo->id.'/featured-image', [
            'image' => UploadedFile::fake()->image('van.jpg'),
        ])->assertOk();

        $this->assertTrue($vehiculo->fresh()->hasMedia('featured_image'));
    }

    public function test_subir_la_imagen_destacada_exige_una_imagen_valida(): void
    {
        Passport::actingAs($this->editor());
        $vehiculo = $this->vehiculo();

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/transport-vehicles/'.$vehiculo->id.'/featured-image', [
                'image' => UploadedFile::fake()->create('doc.pdf', 10),
            ])->assertStatus(422);
    }

    public function test_un_editor_sube_varias_imagenes_a_la_galeria(): void
    {
        Passport::actingAs($this->editor());
        $vehiculo = $this->vehiculo();

        $this->post('/api/v1/transport-vehicles/'.$vehiculo->id.'/gallery', [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertOk();

        $this->assertCount(2, $vehiculo->fresh()->getMedia('gallery'));
    }

    public function test_borrar_una_imagen_de_la_galeria(): void
    {
        Passport::actingAs($this->editor());
        $vehiculo = $this->vehiculo();
        $media = $vehiculo->addMedia(UploadedFile::fake()->image('c.jpg'))->toMediaCollection('gallery');

        $this->apiJson('DELETE', '/api/v1/transport-vehicles/'.$vehiculo->id.'/gallery/'.$media->id)
            ->assertNoContent();

        $this->assertCount(0, $vehiculo->fresh()->getMedia('gallery'));
    }

    public function test_no_se_puede_borrar_una_imagen_de_otro_vehiculo(): void
    {
        Passport::actingAs($this->editor());
        $vehiculoA = $this->vehiculo(['title' => 'A']);
        $vehiculoB = $this->vehiculo(['title' => 'B']);
        $media = $vehiculoA->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('gallery');

        // El id del vehículo en la URL no coincide con el dueño real del media.
        $this->apiJson('DELETE', '/api/v1/transport-vehicles/'.$vehiculoB->id.'/gallery/'.$media->id)
            ->assertNotFound();

        $this->assertCount(1, $vehiculoA->fresh()->getMedia('gallery'));
    }

    public function test_sin_permiso_de_media_no_se_puede_subir_imagenes(): void
    {
        $cliente = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($cliente);
        $vehiculo = $this->vehiculo();

        $this->post('/api/v1/transport-vehicles/'.$vehiculo->id.'/featured-image', [
            'image' => UploadedFile::fake()->image('van.jpg'),
        ])->assertForbidden();
    }
}
