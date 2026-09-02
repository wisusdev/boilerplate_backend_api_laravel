<?php

namespace Tests\Feature\Travel;

use App\Models\GalleryItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Galería del sitio. Sin ninguna prueba hasta ahora; `reorder()` además
 * aceptaba cualquier array sin validar tamaño ni tipo (ver GalleryReorderRequest).
 */
class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function editor(): User
    {
        $user = User::create([
            'username' => 'ed'.uniqid(), 'first_name' => 'Editor', 'last_name' => 'G',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('editor');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function item(int $order = 0): GalleryItem
    {
        $item = GalleryItem::create(['caption' => 'Foto', 'sort_order' => $order]);
        $item->addMedia(UploadedFile::fake()->image('g.jpg'))->toMediaCollection('image');

        return $item;
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── Lectura pública ────────────────────────────────────────────────────────

    public function test_index_lista_la_galeria(): void
    {
        $this->item(0);
        $this->item(1);

        $this->apiJson('GET', '/api/v1/gallery')->assertOk()->assertJsonCount(2, 'data');
    }

    // ─── Alta ─────────────────────────────────────────────────────────────────

    public function test_un_editor_sube_una_sola_imagen(): void
    {
        $this->editor();

        $this->post('/api/v1/gallery', [
            'image' => UploadedFile::fake()->image('foto.jpg'),
            'caption' => 'Playa El Tunco',
        ])->assertOk();

        $this->assertSame(1, GalleryItem::count());
        $this->assertTrue(GalleryItem::first()->hasMedia('image'));
    }

    public function test_un_editor_sube_varias_imagenes_a_la_vez(): void
    {
        $this->editor();

        $this->post('/api/v1/gallery', [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertOk();

        $this->assertSame(2, GalleryItem::count());
    }

    public function test_subir_mas_de_20_imagenes_a_la_vez_se_rechaza(): void
    {
        $this->editor();

        $imagenes = array_map(fn ($i) => UploadedFile::fake()->image("i{$i}.jpg"), range(1, 21));

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/gallery', ['images' => $imagenes])
            ->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_subir_a_la_galeria(): void
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        $this->post('/api/v1/gallery', ['image' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();
    }

    // ─── Borrado ──────────────────────────────────────────────────────────────

    public function test_un_editor_borra_una_foto(): void
    {
        $this->editor();
        $item = $this->item();

        $this->apiJson('DELETE', '/api/v1/gallery/'.$item->id)->assertNoContent();

        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
    }

    // ─── Reordenar ──────────────────────────────────────────────────────────────

    public function test_un_editor_reordena_la_galeria(): void
    {
        $this->editor();
        $a = $this->item(0);
        $b = $this->item(1);

        $this->apiJson('PATCH', '/api/v1/gallery/reorder', [
            'data' => ['type' => 'gallery-reorder', 'attributes' => ['ids' => [$b->id, $a->id]]],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reordenar_con_un_id_inexistente_no_revienta_el_resto(): void
    {
        // Carrera benigna: otro admin borró una foto justo antes. Ese id
        // concreto no actualiza nada, pero el resto del reordenamiento sigue.
        $this->editor();
        $a = $this->item(0);

        $this->apiJson('PATCH', '/api/v1/gallery/reorder', [
            'data' => ['type' => 'gallery-reorder', 'attributes' => ['ids' => ['no-existe-'.uniqid(), $a->id]]],
        ])->assertOk();

        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reordenar_sin_ids_se_rechaza(): void
    {
        $this->editor();

        $this->apiJson('PATCH', '/api/v1/gallery/reorder', [
            'data' => ['type' => 'gallery-reorder', 'attributes' => []],
        ])->assertStatus(422);
    }

    public function test_reordenar_con_ids_duplicados_se_rechaza(): void
    {
        $this->editor();
        $a = $this->item(0);

        $this->apiJson('PATCH', '/api/v1/gallery/reorder', [
            'data' => ['type' => 'gallery-reorder', 'attributes' => ['ids' => [$a->id, $a->id]]],
        ])->assertStatus(422);
    }

    public function test_reordenar_mas_de_500_ids_se_rechaza(): void
    {
        $this->editor();

        $this->apiJson('PATCH', '/api/v1/gallery/reorder', [
            'data' => ['type' => 'gallery-reorder', 'attributes' => ['ids' => array_map('strval', range(1, 501))]],
        ])->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_reordenar(): void
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);
        $a = $this->item(0);

        $this->apiJson('PATCH', '/api/v1/gallery/reorder', [
            'data' => ['type' => 'gallery-reorder', 'attributes' => ['ids' => [$a->id]]],
        ])->assertForbidden();
    }
}
