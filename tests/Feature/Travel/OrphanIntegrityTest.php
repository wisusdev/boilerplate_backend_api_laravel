<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\ProductReview;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Integridad referencial y de ficheros: ningún borrado o edición puede dejar
 * registros apuntando al vacío ni ficheros sin dueño.
 */
class OrphanIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function user(?string $role = null, string $p = 'u'): User
    {
        $user = User::create([
            'username' => $p.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid($p).'@example.com',
            'password' => bcrypt('password123'),
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'Ruta', 'description' => 'd', 'price' => 10,
            'max_capacity' => 5, 'location' => 'L', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function booking(User $user, Tour $tour): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'starts_at' => '2026-12-01 00:00:00',
            'party_size' => 1,
            'total_price' => 10,
            'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── Relaciones polimórficas (sin clave foránea posible) ──────────────────

    public function test_borrar_una_reserva_no_deja_pagos_huerfanos(): void
    {
        $booking = $this->booking($this->user(), $this->tour());
        Payment::create([
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'gateway' => 'manual', 'amount' => 10, 'currency_code' => 'USD', 'status' => 'pending',
        ]);

        $booking->delete();

        $this->assertSame(0, Payment::count());
    }

    public function test_no_se_puede_borrar_un_tour_con_reservas(): void
    {
        $tour = $this->tour();
        $this->booking($this->user(), $tour);

        $this->expectException(\RuntimeException::class);

        try {
            $tour->delete();
        } finally {
            // La reserva sigue intacta: nunca queda apuntando a un tour inexistente.
            $this->assertSame(1, Booking::count());
            $this->assertNotNull(Booking::first()->bookable);
        }
    }

    public function test_borrar_un_tour_sin_reservas_limpia_sus_resenas(): void
    {
        $tour = $this->tour();
        ProductReview::create([
            'user_id' => $this->user()->id,
            'reviewable_type' => Tour::class, 'reviewable_id' => $tour->id,
            'rating' => 5, 'is_approved' => true,
        ]);

        $tour->delete();

        $this->assertSame(0, ProductReview::count());
    }

    // ─── Cascadas SQL que saltaban los eventos de Eloquent ────────────────────

    public function test_borrar_una_categoria_de_gasto_no_arrastra_gastos_ni_recibos(): void
    {
        Storage::fake('public');
        $category = ExpenseCategory::create(['name' => 'Comida', 'is_active' => true]);
        $expense = Expense::create([
            'expense_category_id' => $category->id, 'amount' => 5,
            'spent_at' => now()->toDateString(), 'description' => 'x',
        ]);
        $expense->addMedia(UploadedFile::fake()->image('recibo.jpg'))->toMediaCollection('receipt');

        Passport::actingAs($this->user('admin', 'admin')->fresh());

        // Antes: CASCADE por SQL → el gasto desaparecía y su recibo quedaba huérfano.
        $this->apiJson('DELETE', '/api/v1/expense-categories/'.$category->id)
            ->assertStatus(409);

        $this->assertSame(1, Expense::count());
        $this->assertSame(1, DB::table('media')->count());
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_borrar_un_gasto_elimina_su_recibo(): void
    {
        Storage::fake('public');
        $category = ExpenseCategory::create(['name' => 'Transporte', 'is_active' => true]);
        $expense = Expense::create([
            'expense_category_id' => $category->id, 'amount' => 5,
            'spent_at' => now()->toDateString(), 'description' => 'x',
        ]);
        $expense->addMedia(UploadedFile::fake()->image('recibo.jpg'))->toMediaCollection('receipt');

        $expense->delete();

        $this->assertSame(0, DB::table('media')->count());
    }

    // ─── Usuarios: historial y ficheros ───────────────────────────────────────

    public function test_dar_de_baja_un_usuario_conserva_su_historial_legible(): void
    {
        $user = $this->user();
        $booking = $this->booking($user, $this->tour());

        $user->delete(); // soft delete

        $booking->refresh();
        $this->assertNotNull($booking->user, 'La reserva debe seguir mostrando su cliente.');
        $this->assertSame($user->id, $booking->user->id);
    }

    public function test_dar_de_baja_un_usuario_conserva_su_avatar_y_revoca_sesiones(): void
    {
        Storage::fake('public');
        $user = $this->user();
        Storage::disk('public')->put('uploads/avatar.webp', 'x');
        $user->forceFill(['avatar' => '/uploads/avatar.webp'])->save();

        DB::table('oauth_access_tokens')->insert([
            'id' => 'tok-'.uniqid(), 'user_id' => $user->id,
            'client_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'sesion', 'scopes' => '[]', 'revoked' => false,
            'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addWeek(),
        ]);

        $user->delete();

        // El fichero acompaña a la fila: si se restaura, el avatar sigue ahí.
        Storage::disk('public')->assertExists('uploads/avatar.webp');
        $this->assertSame(0, $user->tokens()->where('revoked', false)->count());
    }

    public function test_el_borrado_definitivo_de_un_usuario_elimina_su_avatar(): void
    {
        Storage::fake('public');
        $user = $this->user();
        Storage::disk('public')->put('uploads/avatar.webp', 'x');
        $user->forceFill(['avatar' => '/uploads/avatar.webp'])->save();

        $user->forceDelete();

        Storage::disk('public')->assertMissing('uploads/avatar.webp');
    }

    public function test_cambiar_el_avatar_elimina_el_anterior(): void
    {
        Storage::fake('public');
        $user = $this->user();
        Storage::disk('public')->put('uploads/viejo.webp', 'x');
        Storage::disk('public')->put('uploads/nuevo.webp', 'y');
        $user->forceFill(['avatar' => '/uploads/viejo.webp'])->save();

        $user->update(['avatar' => '/uploads/nuevo.webp']);

        Storage::disk('public')->assertMissing('uploads/viejo.webp');
        Storage::disk('public')->assertExists('uploads/nuevo.webp');
    }

    public function test_el_historial_de_reservas_bloquea_el_borrado_definitivo(): void
    {
        $user = $this->user();
        $this->booking($user, $this->tour());

        // FK RESTRICT: el historial contable no se destruye en cascada.
        $this->expectException(QueryException::class);
        $user->forceDelete();
    }

    // ─── Comando de limpieza ──────────────────────────────────────────────────

    /**
     * Regresión: el proyecto usa un PathGenerator propio (DatePathGenerator,
     * año/mes/día) en lugar del layout por id de Spatie. Una versión anterior de
     * este comando asumía el layout por defecto y marcaba TODO el árbol de
     * subidas como huérfano: ejecutar --fix habría borrado todas las imágenes.
     */
    public function test_el_comando_no_marca_como_huerfanos_los_ficheros_vivos(): void
    {
        Storage::fake('public');
        $category = ExpenseCategory::create(['name' => 'Comida', 'is_active' => true]);
        $expense = Expense::create([
            'expense_category_id' => $category->id, 'amount' => 5,
            'spent_at' => now()->toDateString(), 'description' => 'x',
        ]);
        $expense->addMedia(UploadedFile::fake()->image('recibo.jpg'))->toMediaCollection('receipt');

        $media = $expense->getFirstMedia('receipt');
        $path = ltrim($media->getPathRelativeToRoot(), '/');

        // El fichero vive en un árbol de fecha (2026/08/31/…). El layout por
        // defecto de Spatie sería "{id}/fichero", que no casa con este patrón.
        // Comprobar la forma de la ruta evita la fragilidad de buscar "{id}/":
        // los días como el 31 contienen el "1/" del id y daban un falso fallo.
        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/\d{2}/[^/]+$#', $path);
        Storage::disk('public')->assertExists($path);

        $this->artisan('integrity:scan', ['--fix' => true])->assertSuccessful();

        Storage::disk('public')->assertExists($path);
        $this->assertSame(1, DB::table('media')->count());
    }

    public function test_el_comando_borra_un_fichero_de_media_sin_fila(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('2026/01/01/huerfano-abc123.jpeg', 'contenido');

        $this->artisan('integrity:scan', ['--fix' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('2026/01/01/huerfano-abc123.jpeg');
    }

    public function test_el_comando_no_toca_logos_ni_avatares_referenciados(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.png', 'x');
        $user = $this->user();
        Storage::disk('public')->put('uploads/2026/08/28/mi-avatar.webp', 'y');
        $user->forceFill(['avatar' => '/uploads/2026/08/28/mi-avatar.webp'])->save();

        $this->artisan('integrity:scan', ['--fix' => true])->assertSuccessful();

        Storage::disk('public')->assertExists('settings/logo.png');
        Storage::disk('public')->assertExists('uploads/2026/08/28/mi-avatar.webp');
    }

    public function test_el_comando_de_integridad_detecta_y_limpia_huerfanos(): void
    {
        Storage::fake('public');
        $booking = $this->booking($this->user(), $this->tour());

        // Se fabrica un huérfano saltándose Eloquent, como haría una cascada SQL.
        Payment::create([
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'gateway' => 'manual', 'amount' => 10, 'currency_code' => 'USD', 'status' => 'pending',
        ]);
        DB::table('bookings')->where('id', $booking->id)->delete();

        $this->artisan('integrity:scan')
            ->expectsOutputToContain('Pagos sin reserva')
            ->assertSuccessful();

        $this->assertSame(1, Payment::count(), 'Sin --fix no debe borrar nada.');

        $this->artisan('integrity:scan', ['--fix' => true])->assertSuccessful();

        $this->assertSame(0, Payment::count());
    }
}
