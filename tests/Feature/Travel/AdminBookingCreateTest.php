<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminBookingCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Siembra el catálogo real de permisos y roles (admin recibe todos).
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true]);
    }

    private function makeUser(string $email, ?string $role = null): User
    {
        $u = User::create([
            'username' => 'u'.uniqid(), 'first_name' => 'T', 'last_name' => 'U',
            'email' => $email, 'password' => bcrypt('password123'),
        ]);
        if ($role) {
            $u->assignRole($role);
        }

        return $u;
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'Tour', 'description' => 'd', 'price' => 50, 'max_capacity' => 10,
            'location' => 'SV', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function postBooking(array $attributes)
    {
        return $this->call('POST', '/api/v1/bookings', [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode(['data' => ['type' => 'bookings', 'attributes' => $attributes]]));
    }

    public function test_admin_crea_reserva_para_cliente_nuevo_por_correo(): void
    {
        $admin = $this->makeUser('admin@example.com', 'admin');
        $tour = $this->tour();
        Passport::actingAs($admin);

        $res = $this->postBooking([
            'booking_type' => 'tour', 'accept_terms' => true,
            'tour_id' => $tour->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'pax_count' => 2,
            'customer' => ['name' => 'Ana Pérez', 'email' => 'walkin@example.com', 'phone' => '+503 7000 0000'],
        ]);

        $res->assertStatus(201);
        $customer = User::where('email', 'walkin@example.com')->first();
        $this->assertNotNull($customer, 'Debe crear el usuario cliente');
        $this->assertTrue($customer->hasRole('user'));
        $this->assertDatabaseHas('bookings', ['user_id' => $customer->id, 'bookable_id' => $tour->id]);
    }

    public function test_admin_crea_reserva_para_cliente_existente(): void
    {
        $admin = $this->makeUser('admin2@example.com', 'admin');
        $client = $this->makeUser('cliente@example.com', 'user');
        $tour = $this->tour();
        Passport::actingAs($admin);

        $res = $this->postBooking([
            'booking_type' => 'tour', 'accept_terms' => true,
            'tour_id' => $tour->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'pax_count' => 1,
            'customer' => ['id' => $client->id],
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('bookings', ['user_id' => $client->id, 'bookable_id' => $tour->id]);
    }

    public function test_cliente_no_puede_asignar_reserva_a_otro(): void
    {
        $client = $this->makeUser('self@example.com', 'user');
        $other = $this->makeUser('other@example.com', 'user');
        $tour = $this->tour();
        Passport::actingAs($client);

        $res = $this->postBooking([
            'booking_type' => 'tour', 'accept_terms' => true,
            'tour_id' => $tour->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'pax_count' => 1,
            'customer' => ['id' => $other->id],
        ]);

        $res->assertStatus(201);
        // La reserva queda a nombre del propio cliente, ignorando el customer enviado.
        $this->assertDatabaseHas('bookings', ['user_id' => $client->id]);
        $this->assertDatabaseMissing('bookings', ['user_id' => $other->id]);
    }

    // ─── C-3: confirmar sin cobrar deja rastro ────────────────────────────────

    public function test_confirmar_una_reserva_presencial_sin_pago_avisa_al_back_office(): void
    {
        // Puede ser una decisión legítima (cobro en efectivo el día del
        // servicio), pero antes no quedaba ni aviso ni registro de que alguien
        // la hubiera tomado a sabiendas.
        Notification::fake();
        $admin = $this->makeUser('admin4@example.com', 'admin');
        $tour = $this->tour();
        Passport::actingAs($admin);

        $res = $this->postBooking([
            'booking_type' => 'tour', 'accept_terms' => true,
            'tour_id' => $tour->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'pax_count' => 1,
            'customer' => ['name' => 'Sin Pago', 'email' => 'sinpago@example.com'],
            'status' => 'confirmed',
        ]);

        $res->assertStatus(201);
        $this->assertSame(Booking::STATUS_CONFIRMED, Booking::first()->status);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            AdminAlertNotification::class,
            fn ($n, $ch, $notifiable) => str_contains(
                $n->toMail($notifiable)->subject,
                'confirmada con saldo pendiente'
            )
        );
    }

    public function test_confirmar_una_reserva_ya_cobrada_no_dispara_el_aviso(): void
    {
        Notification::fake();
        $admin = $this->makeUser('admin5@example.com', 'admin');
        $tour = $this->tour(); // price 50
        Passport::actingAs($admin);

        $res = $this->postBooking([
            'booking_type' => 'tour', 'accept_terms' => true,
            'tour_id' => $tour->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'pax_count' => 1,
            'customer' => ['name' => 'Ya Pagó', 'email' => 'yapago@example.com'],
        ]);
        $booking = Booking::first();

        Payment::create([
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'gateway' => 'manual', 'method' => 'cash', 'amount' => $booking->total_price,
            'currency_code' => 'USD', 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->apiJsonPatch("/api/v1/bookings/{$booking->id}", ['status' => 'confirmed']);

        Notification::assertNotSentTo(new AnonymousNotifiable, AdminAlertNotification::class);
    }

    private function apiJsonPatch(string $uri, array $attributes)
    {
        return $this->call('PATCH', $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode(['data' => ['type' => 'bookings', 'id' => '1', 'attributes' => $attributes]]));
    }
}
