<?php

namespace Tests\Feature\Auth;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\VerifyDeleteAccountNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Cierre de cuenta en dos pasos.
 *
 * Requisito de negocio: la cuenta NO se borra de la base. Se desactiva (soft
 * delete) para conservar el historial por obligaciones de auditoría.
 */
class CloseAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    /** El endpoint exige las cabeceras JSON:API, igual que las envía el frontend. */
    private function confirmar(string $token): TestResponse
    {
        return $this->call('DELETE', '/api/v1/account/delete-account-verify', [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode(['data' => ['type' => 'delete-account', 'attributes' => ['token' => $token]]]));
    }

    private function cliente(): User
    {
        $user = User::create([
            'username' => 'baja'.uniqid(), 'first_name' => 'Rosa', 'last_name' => 'Baja',
            'email' => uniqid('baja').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('user');
        Passport::actingAs($user->fresh());

        return $user;
    }

    public function test_solicitar_la_baja_envia_el_enlace_y_no_borra_nada(): void
    {
        Notification::fake();
        $user = $this->cliente();

        $this->postJson('/api/v1/account/delete-account')->assertOk();

        Notification::assertSentTo($user, VerifyDeleteAccountNotification::class);
        $this->assertNull($user->fresh()->deleted_at, 'Pedir el enlace no debe cerrar la cuenta.');
        $this->assertDatabaseHas('account_deletion_tokens', ['email' => $user->email]);
    }

    public function test_el_token_se_guarda_hasheado(): void
    {
        Notification::fake();
        $user = $this->cliente();

        $this->postJson('/api/v1/account/delete-account')->assertOk();

        $fila = DB::table('account_deletion_tokens')->where('email', $user->email)->first();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fila->token);
    }

    public function test_confirmar_desactiva_la_cuenta_pero_conserva_el_historial(): void
    {
        Notification::fake();
        $user = $this->cliente();

        $tour = Tour::create([
            'title' => 'Ruta', 'description' => 'd', 'price' => 65, 'max_capacity' => 5,
            'location' => 'L', 'currency_code' => 'USD', 'is_active' => true,
        ]);
        $booking = Booking::create([
            'user_id' => $user->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(10), 'party_size' => 1, 'total_price' => 65,
            'currency_code' => 'USD', 'status' => Booking::STATUS_CONFIRMED,
        ]);
        Payment::create([
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'gateway' => 'wompi', 'amount' => 65, 'currency_code' => 'USD',
            'status' => 'paid', 'paid_at' => now(),
        ]);

        $token = 'token-de-prueba';
        DB::table('account_deletion_tokens')->insert([
            'email' => $user->email, 'token' => hash('sha256', $token), 'created_at' => now(),
        ]);

        $this->confirmar($token)->assertOk();

        // La fila SIGUE en la base, marcada como borrada.
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotNull(User::withTrashed()->find($user->id));

        // El historial contable se conserva íntegro.
        $this->assertSame(1, Booking::where('user_id', $user->id)->count());
        $this->assertSame(1, Payment::where('payable_id', $booking->id)->count());

        // El token se consumió.
        $this->assertDatabaseMissing('account_deletion_tokens', ['email' => $user->email]);
    }

    public function test_no_se_puede_usar_el_token_de_otra_persona(): void
    {
        $ajeno = User::create([
            'username' => 'ajeno', 'first_name' => 'A', 'last_name' => 'B',
            'email' => 'ajeno@example.com', 'password' => bcrypt('password123'),
        ]);
        DB::table('account_deletion_tokens')->insert([
            'email' => $ajeno->email, 'token' => hash('sha256', 'token-ajeno'), 'created_at' => now(),
        ]);

        $this->cliente();

        $this->confirmar('token-ajeno')->assertForbidden();

        $this->assertNull($ajeno->fresh()->deleted_at);
    }

    public function test_un_token_caducado_se_rechaza(): void
    {
        $user = $this->cliente();
        DB::table('account_deletion_tokens')->insert([
            'email' => $user->email, 'token' => hash('sha256', 'viejo'),
            'created_at' => now()->subHours(7),
        ]);

        $this->confirmar('viejo')->assertStatus(422);

        $this->assertNull($user->fresh()->deleted_at);
    }
}
