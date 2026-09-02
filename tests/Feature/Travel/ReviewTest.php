<?php

namespace Tests\Feature\Travel;

use App\Models\Review;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Testimonios curados a mano (distintos de ProductReview, las reseñas de
 * clientes ligadas a una reserva, que ya tienen su propia cobertura).
 */
class ReviewTest extends TestCase
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
            'username' => 'ed'.uniqid(), 'first_name' => 'Editor', 'last_name' => 'R',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('editor');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function review(array $extra = []): Review
    {
        return Review::create(array_merge([
            'name' => 'Ana Pérez', 'quote' => 'Excelente experiencia', 'rating' => 5, 'is_active' => true,
        ], $extra));
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── Lectura pública ────────────────────────────────────────────────────────

    public function test_index_solo_lista_testimonios_activos_ordenados(): void
    {
        $this->review(['name' => 'Activo', 'is_active' => true, 'sort_order' => 1]);
        $this->review(['name' => 'Inactivo', 'is_active' => false]);

        $data = $this->apiJson('GET', '/api/v1/reviews')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Activo', $data[0]['attributes']['name']);
    }

    // ─── Alta ─────────────────────────────────────────────────────────────────

    public function test_un_editor_crea_un_testimonio(): void
    {
        $this->editor();

        $this->apiJson('POST', '/api/v1/reviews', [
            'data' => ['type' => 'reviews', 'attributes' => ['name' => 'Carlos López', 'quote' => 'Increíble tour', 'rating' => 5]],
        ])->assertCreated()->assertJsonPath('data.attributes.name', 'Carlos López');

        $this->assertDatabaseHas('reviews', ['name' => 'Carlos López']);
    }

    public function test_crear_un_testimonio_exige_nombre_y_texto(): void
    {
        $this->editor();

        $this->apiJson('POST', '/api/v1/reviews', ['data' => ['type' => 'reviews', 'attributes' => ['rating' => 5]]])
            ->assertStatus(422);
    }

    public function test_la_calificacion_esta_acotada_entre_1_y_5(): void
    {
        $this->editor();

        $this->apiJson('POST', '/api/v1/reviews', [
            'data' => ['type' => 'reviews', 'attributes' => ['name' => 'X', 'quote' => 'Y', 'rating' => 9]],
        ])->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_crear_un_testimonio(): void
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/reviews', [
            'data' => ['type' => 'reviews', 'attributes' => ['name' => 'X', 'quote' => 'Y']],
        ])->assertForbidden();
    }

    // ─── Edición y borrado ──────────────────────────────────────────────────────

    public function test_un_editor_actualiza_un_testimonio(): void
    {
        $this->editor();
        $review = $this->review();

        $this->apiJson('PATCH', '/api/v1/reviews/'.$review->id, [
            'data' => ['id' => (string) $review->id, 'type' => 'reviews', 'attributes' => ['quote' => 'Actualizado']],
        ])->assertOk()->assertJsonPath('data.attributes.quote', 'Actualizado');
    }

    public function test_un_editor_borra_un_testimonio(): void
    {
        $this->editor();
        $review = $this->review();

        $this->apiJson('DELETE', '/api/v1/reviews/'.$review->id)->assertNoContent();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
