<?php

namespace Tests\Feature\Base;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Support\LegalDocuments;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Términos, privacidad y cancelación: lectura pública, edición desde el panel
 * y constancia de aceptación al registrarse y al reservar.
 */
class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        SiteSettings::flush();
    }

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'username' => $role.uniqid(), 'first_name' => 'Test', 'last_name' => 'Legal',
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

    private function editar(string $slug, string $content): TestResponse
    {
        return $this->apiJson('PATCH', "/api/v1/legal/{$slug}", ['data' => [
            'type' => 'legal-documents', 'id' => $slug, 'attributes' => ['content' => $content],
        ]]);
    }

    public function test_sin_editar_se_sirve_el_borrador_con_los_datos_del_sitio(): void
    {
        Setting::create(['key' => 'app', 'value' => json_encode([
            'contact_email' => 'hola@cusgo.test',
            'booking_cancellation_hours' => 48,
        ])]);

        $res = $this->apiJson('GET', '/api/v1/legal/cancelacion')->assertOk();

        $res->assertJsonPath('data.attributes.is_draft', true);
        $res->assertJsonPath('meta.version', LegalDocuments::DRAFT_VERSION);
        $html = $res->json('data.attributes.html');
        $this->assertStringContainsString('hola@cusgo.test', $html);
        $this->assertStringContainsString('48 horas', $html);
        // Lo que no está configurado queda resaltado como pendiente.
        $this->assertStringContainsString('<mark class="legal-pending">[pendiente:', $html);
        // La tabla de reembolsos (GFM) se convierte en tabla.
        $this->assertStringContainsString('<table>', $html);
    }

    public function test_el_listado_publico_incluye_los_tres_documentos(): void
    {
        $this->apiJson('GET', '/api/v1/legal')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', 'terminos');
    }

    public function test_un_documento_inexistente_da_404(): void
    {
        $this->apiJson('GET', '/api/v1/legal/cookies')->assertNotFound();
    }

    public function test_el_admin_edita_un_texto_y_cambia_la_version(): void
    {
        $this->actingAsRole('admin');

        $res = $this->editar('terminos', "## Nuevos términos\n\nEscríbenos a {{correo}}.")->assertOk();

        $res->assertJsonPath('data.attributes.is_draft', false);
        $this->assertStringContainsString('<h2>Nuevos términos</h2>', $res->json('data.attributes.html'));
        $this->assertNotSame(LegalDocuments::DRAFT_VERSION, $res->json('meta.version'));
        $this->assertSame($res->json('data.attributes.updated_at'), LegalDocuments::version());
    }

    public function test_el_texto_no_puede_inyectar_html_ni_enlaces_javascript(): void
    {
        $this->actingAsRole('admin');

        $html = $this->editar('privacidad', "<script>alert(1)</script>\n\n[clic](javascript:alert(1)) <img src=x onerror=alert(1)>")
            ->assertOk()
            ->json('data.attributes.html');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_un_cliente_no_puede_editar_los_textos(): void
    {
        $this->actingAsRole('user');

        $this->editar('terminos', 'Nada')->assertForbidden();
    }

    public function test_registrarse_exige_aceptar_y_guarda_la_version(): void
    {
        $datos = [
            'first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@example.com',
            'password' => 'secure123', 'password_confirmation' => 'secure123',
        ];

        // El registro responde con el formato de errores de Laravel, no JSON:API.
        $this->apiJson('POST', '/api/v1/auth/register', ['data' => ['type' => 'users', 'attributes' => $datos]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.attributes.accept_terms');

        $this->apiJson('POST', '/api/v1/auth/register', ['data' => ['type' => 'users', 'attributes' => $datos + ['accept_terms' => true]]])
            ->assertCreated();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(LegalDocuments::DRAFT_VERSION, $user->terms_version);
    }

    public function test_reservar_exige_aceptar_y_guarda_la_version(): void
    {
        $this->actingAsRole('user');
        $tour = Tour::create([
            'title' => 'Volcán', 'description' => 'd', 'price' => 50,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ]);
        $reserva = fn (array $extra) => $this->apiJson('POST', '/api/v1/bookings', ['data' => [
            'type' => 'bookings',
            'attributes' => ['booking_type' => 'tour', 'tour_id' => $tour->id, 'booking_date' => '2099-06-01', 'pax_count' => 2] + $extra,
        ]]);

        $reserva([])->assertJsonApiValidationErrors('data.attributes.accept_terms');
        $reserva(['accept_terms' => false])->assertJsonApiValidationErrors('data.attributes.accept_terms');

        $id = $reserva(['accept_terms' => true])->assertCreated()->json('data.id');

        $booking = Booking::findOrFail($id);
        $this->assertNotNull($booking->terms_accepted_at);
        $this->assertSame(LegalDocuments::DRAFT_VERSION, $booking->terms_version);
    }
}
