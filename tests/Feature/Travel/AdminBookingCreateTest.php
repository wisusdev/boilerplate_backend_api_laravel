<?php

namespace Tests\Feature\Travel;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminBookingCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'super-admin', 'user'] as $r) {
            Role::findOrCreate($r, 'api');
        }
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
            'booking_type' => 'tour',
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
            'booking_type' => 'tour',
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
            'booking_type' => 'tour',
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
}
