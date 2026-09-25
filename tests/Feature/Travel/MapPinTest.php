<?php

namespace Tests\Feature\Travel;

use App\Models\MapPin;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Mapa público de pines: gestión desde el panel, lectura pública y la
 * incrustación segura de videos de Instagram.
 */
class MapPinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'username' => $role.uniqid(), 'first_name' => 'Test', 'last_name' => 'Map',
            'email' => uniqid($role).'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole($role);
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function crear(array $attrs = []): TestResponse
    {
        return $this->apiJson('POST', '/api/v1/map-pins', ['data' => ['type' => 'map-pins', 'attributes' => array_merge([
            'title' => 'Volcán de Santa Ana',
            'description' => 'Cráter con laguna turquesa. Salida desde el Parque Nacional Los Volcanes.',
            'latitude' => 13.8536,
            'longitude' => -89.6300,
            'icon' => 'binoculars-fill',
            'color' => '#e4572e',
        ], $attrs)]]);
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'Ruta de los Volcanes', 'description' => 'd', 'price' => 65,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    public function test_un_editor_crea_un_pin_con_un_reel_de_instagram(): void
    {
        $this->actingAsRole('editor');
        $tour = $this->tour();

        $this->crear([
            'tour_id' => $tour->id,
            // Tal como se copia de la app: con el usuario delante y parámetros de rastreo.
            'instagram_url' => 'https://www.instagram.com/vamospues.sv/reel/C8xYz_12AbC/?igsh=MWx0bG9wNmRk',
        ])->assertCreated()
            ->assertJsonPath('data.attributes.instagram_embed_url', 'https://www.instagram.com/reel/C8xYz_12AbC/embed/')
            ->assertJsonPath('data.attributes.instagram_permalink', 'https://www.instagram.com/reel/C8xYz_12AbC/')
            ->assertJsonPath('data.attributes.tour.title', 'Ruta de los Volcanes');

        $pin = MapPin::firstOrFail();
        $this->assertSame('reel', $pin->instagram_type);
        $this->assertSame('C8xYz_12AbC', $pin->instagram_code);
        $this->assertSame(1, $pin->sort_order);
    }

    public function test_reconoce_posts_reels_y_videos_y_nada_mas(): void
    {
        $this->assertSame(['type' => 'p', 'code' => 'DAbc123xyz'], MapPin::parseInstagram('https://instagram.com/p/DAbc123xyz/'));
        $this->assertSame(['type' => 'reel', 'code' => 'DAbc123xyz'], MapPin::parseInstagram('https://www.instagram.com/reels/DAbc123xyz'));
        $this->assertSame(['type' => 'tv', 'code' => 'DAbc123xyz'], MapPin::parseInstagram('https://www.instagram.com/tv/DAbc123xyz/'));

        $this->assertNull(MapPin::parseInstagram('https://www.instagram.com/vamospues.sv/'));           // perfil
        $this->assertNull(MapPin::parseInstagram('https://www.instagram.com/stories/vamospues/123/'));  // historia
        $this->assertNull(MapPin::parseInstagram('https://instagram.com.evil.test/p/DAbc123xyz/'));    // otro dominio
        $this->assertNull(MapPin::parseInstagram('javascript://www.instagram.com/p/DAbc123xyz/'));
    }

    public function test_rechaza_enlaces_que_no_son_seguros_ni_validos(): void
    {
        $this->actingAsRole('editor');

        $this->crear(['instagram_url' => 'https://www.instagram.com/vamospues.sv/'])->assertStatus(422)
            ->assertJsonFragment(['detail' => 'El enlace de Instagram debe ser un post, reel o video (instagram.com/p/…, /reel/… o /tv/…). Para otro tipo de enlace usa "Enlace".']);
        $this->crear(['link_url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->crear(['icon' => 'x" onmouseover="alert(1)'])->assertStatus(422);
        $this->crear(['color' => 'red; background:url(x)'])->assertStatus(422);
        $this->crear(['latitude' => 120])->assertStatus(422);

        $this->assertSame(0, MapPin::count());
    }

    public function test_el_mapa_publico_muestra_solo_los_pines_activos_en_orden(): void
    {
        MapPin::create(['title' => 'Oculto', 'latitude' => 13.5, 'longitude' => -89.2, 'is_active' => false, 'sort_order' => 0]);
        MapPin::create(['title' => 'Segundo', 'latitude' => 13.6, 'longitude' => -89.3, 'sort_order' => 2,
            'link_url' => 'https://www.youtube.com/watch?v=abc', 'link_label' => 'Ver en YouTube']);
        MapPin::create(['title' => 'Primero', 'latitude' => 13.7, 'longitude' => -89.4, 'sort_order' => 1]);

        // Sin sesión.
        $this->apiJson('GET', '/api/v1/map-pins')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.title', 'Primero')
            ->assertJsonPath('data.1.attributes.link_url', 'https://www.youtube.com/watch?v=abc')
            ->assertJsonPath('data.1.attributes.instagram_embed_url', null);
    }

    public function test_el_panel_ve_tambien_los_ocultos_y_solo_con_permiso(): void
    {
        MapPin::create(['title' => 'Oculto', 'latitude' => 13.5, 'longitude' => -89.2, 'is_active' => false]);

        $this->apiJson('GET', '/api/v1/map-pins/manage')->assertUnauthorized();

        $this->actingAsRole('guia');
        $this->apiJson('GET', '/api/v1/map-pins/manage')->assertForbidden();
        $this->crear()->assertForbidden();

        $this->actingAsRole('editor');
        $this->apiJson('GET', '/api/v1/map-pins/manage')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_editar_quita_el_video_y_borrar_elimina_el_pin(): void
    {
        $this->actingAsRole('admin');
        $this->crear(['instagram_url' => 'https://www.instagram.com/p/DAbc123xyz/'])->assertCreated();
        $pin = MapPin::firstOrFail();

        $this->apiJson('PATCH', "/api/v1/map-pins/{$pin->id}", ['data' => [
            'type' => 'map-pins', 'id' => (string) $pin->id,
            'attributes' => ['instagram_url' => null, 'is_active' => false],
        ]])->assertOk()
            ->assertJsonPath('data.attributes.instagram_embed_url', null)
            ->assertJsonPath('data.attributes.is_active', false)
            ->assertJsonPath('data.attributes.title', 'Volcán de Santa Ana');

        $this->apiJson('DELETE', "/api/v1/map-pins/{$pin->id}")->assertNoContent();
        $this->assertSame(0, MapPin::count());
    }

    public function test_el_pin_puede_llevar_una_imagen(): void
    {
        $this->actingAsRole('editor');
        $this->crear()->assertCreated();
        $pin = MapPin::firstOrFail();

        $this->post("/api/v1/map-pins/{$pin->id}/image", ['image' => UploadedFile::fake()->image('laguna.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.attributes.image_url', fn ($url) => is_string($url) && str_contains($url, 'laguna'));

        $this->apiJson('DELETE', "/api/v1/map-pins/{$pin->id}/image")->assertOk()
            ->assertJsonPath('data.attributes.image_url', null);
    }
}
