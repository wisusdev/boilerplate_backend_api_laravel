<?php

namespace Tests\Feature\Travel;

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Índice, ficha y alta de monedas. La edición (incluida la regla de "solo una
 * por defecto") ya la cubre CatalogDeletionTest.
 */
class CurrencyCatalogTest extends TestCase
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
            'username' => 'ed'.uniqid(), 'first_name' => 'Editor', 'last_name' => 'M',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('editor');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function moneda(array $extra = []): Currency
    {
        return Currency::create(array_merge([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
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

    public function test_index_solo_lista_monedas_activas(): void
    {
        $this->moneda(['code' => 'USD']);
        $this->moneda(['code' => 'EUR', 'is_default' => false, 'is_active' => false]);

        $data = $this->apiJson('GET', '/api/v1/currencies')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('USD', $data[0]['attributes']['code']);
    }

    public function test_show_devuelve_una_moneda_activa(): void
    {
        $usd = $this->moneda();

        $this->apiJson('GET', '/api/v1/currencies/'.$usd->id)
            ->assertOk()
            ->assertJsonPath('data.attributes.code', 'USD');
    }

    public function test_show_devuelve_404_para_una_moneda_inactiva(): void
    {
        $eur = $this->moneda(['code' => 'EUR', 'is_default' => false, 'is_active' => false]);

        $this->apiJson('GET', '/api/v1/currencies/'.$eur->id)->assertNotFound();
    }

    // ─── Alta ─────────────────────────────────────────────────────────────────

    public function test_un_editor_da_de_alta_una_moneda(): void
    {
        $this->editor();

        // Laravel marca 201 solo cuando el modelo devuelto conserva
        // `wasRecentlyCreated` (updateOrCreate lo hace; un ->fresh() posterior
        // lo perdería).
        $this->apiJson('POST', '/api/v1/currencies', [
            'data' => ['type' => 'currencies', 'attributes' => [
                'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.92,
            ]],
        ])->assertCreated()->assertJsonPath('data.attributes.code', 'EUR');

        $this->assertDatabaseHas('currencies', ['code' => 'EUR']);
    }

    public function test_no_se_puede_dar_de_alta_una_moneda_con_codigo_repetido(): void
    {
        $this->editor();
        $this->moneda(['code' => 'USD']);

        $this->apiJson('POST', '/api/v1/currencies', [
            'data' => ['type' => 'currencies', 'attributes' => [
                'code' => 'USD', 'name' => 'US Dollar (otro)', 'symbol' => '$', 'rate_to_usd' => 1,
            ]],
        ])->assertStatus(422);
    }

    public function test_dar_de_alta_una_moneda_por_defecto_desactiva_la_anterior(): void
    {
        $this->editor();
        $usd = $this->moneda();

        $this->apiJson('POST', '/api/v1/currencies', [
            'data' => ['type' => 'currencies', 'attributes' => [
                'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.92, 'is_default' => true,
            ]],
        ])->assertCreated();

        $this->assertFalse((bool) $usd->fresh()->is_default);
    }

    public function test_dar_de_alta_una_moneda_exige_una_tasa_de_cambio_positiva(): void
    {
        $this->editor();

        $this->apiJson('POST', '/api/v1/currencies', [
            'data' => ['type' => 'currencies', 'attributes' => [
                'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0,
            ]],
        ])->assertStatus(422);
    }

    public function test_sin_permiso_no_se_puede_dar_de_alta_una_moneda(): void
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/currencies', [
            'data' => ['type' => 'currencies', 'attributes' => [
                'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_usd' => 0.92,
            ]],
        ])->assertForbidden();
    }
}
