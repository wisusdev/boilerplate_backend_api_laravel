<?php

namespace Tests\Feature\Travel;

use App\Models\TourCategory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TourCategoryTest extends TestCase
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
            'username' => 'ed'.uniqid(), 'first_name' => 'Editor', 'last_name' => 'C',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('editor');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function categoria(array $extra = []): TourCategory
    {
        return TourCategory::create(array_merge(['name' => 'Volcanes', 'is_active' => true], $extra));
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── Lectura pública ────────────────────────────────────────────────────────

    public function test_index_solo_lista_categorias_activas(): void
    {
        $this->categoria(['name' => 'Activa', 'is_active' => true]);
        $this->categoria(['name' => 'Inactiva', 'is_active' => false]);

        $data = $this->apiJson('GET', '/api/v1/tour-categories')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Activa', $data[0]['attributes']['name']);
    }

    // ─── Alta ─────────────────────────────────────────────────────────────────

    public function test_un_editor_crea_una_categoria_y_genera_el_slug(): void
    {
        $this->editor();

        $this->apiJson('POST', '/api/v1/tour-categories', [
            'data' => ['type' => 'tour_categories', 'attributes' => ['name' => 'Playas y Surf']],
        ])->assertCreated()->assertJsonPath('data.attributes.slug', 'playas-y-surf');
    }

    public function test_no_se_pueden_crear_dos_categorias_con_el_mismo_nombre(): void
    {
        $this->editor();
        $this->categoria(['name' => 'Volcanes']);

        $this->apiJson('POST', '/api/v1/tour-categories', [
            'data' => ['type' => 'tour_categories', 'attributes' => ['name' => 'Volcanes']],
        ])->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_crear_una_categoria(): void
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/tour-categories', [
            'data' => ['type' => 'tour_categories', 'attributes' => ['name' => 'X']],
        ])->assertForbidden();
    }

    // ─── Edición y borrado ──────────────────────────────────────────────────────

    public function test_un_editor_actualiza_una_categoria(): void
    {
        $this->editor();
        $cat = $this->categoria();

        $this->apiJson('PATCH', '/api/v1/tour-categories/'.$cat->id, [
            'data' => ['id' => (string) $cat->id, 'type' => 'tour_categories', 'attributes' => ['name' => 'Volcanes Activos']],
        ])->assertOk()->assertJsonPath('data.attributes.name', 'Volcanes Activos');
    }

    public function test_editar_una_categoria_no_choca_con_su_propio_nombre(): void
    {
        $this->editor();
        $cat = $this->categoria(['name' => 'Volcanes']);

        // Reenviar el mismo nombre no debe dar error de unicidad.
        $this->apiJson('PATCH', '/api/v1/tour-categories/'.$cat->id, [
            'data' => ['id' => (string) $cat->id, 'type' => 'tour_categories', 'attributes' => ['name' => 'Volcanes']],
        ])->assertOk();
    }

    public function test_un_editor_borra_una_categoria(): void
    {
        $this->editor();
        $cat = $this->categoria();

        $this->apiJson('DELETE', '/api/v1/tour-categories/'.$cat->id)->assertNoContent();

        $this->assertDatabaseMissing('tour_categories', ['id' => $cat->id]);
    }
}
