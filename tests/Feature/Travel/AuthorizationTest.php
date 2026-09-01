<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Cubre las correcciones de control de acceso:
 *  - Endpoints admin de escritura protegidos por rol.
 *  - IDOR en bookings/payments (un usuario no accede a recursos ajenos).
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $userCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Siembra el catálogo real de permisos y roles (admin recibe todos).
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function makeUser(?string $role = null): User
    {
        $this->userCounter++;
        $user = User::create([
            'username' => 'user'.$this->userCounter,
            'first_name' => 'Test',
            'last_name' => 'User'.$this->userCounter,
            'email' => 'user'.$this->userCounter.'@example.com',
            'password' => bcrypt('password123'),
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call(
            $method,
            $uri,
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.api+json',
                'CONTENT_TYPE' => 'application/vnd.api+json',
            ],
            json_encode($payload)
        );
    }

    private function apiGet(string $uri): TestResponse
    {
        return $this->call('GET', $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ]);
    }

    private function makeTour(): Tour
    {
        return Tour::create([
            'title' => 'Sample tour',
            'description' => 'Desc',
            'price' => 50,
            'max_capacity' => 10,
            'location' => 'San Salvador',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
    }

    private function makeBookingFor(User $user): Booking
    {
        $tour = $this->makeTour();

        return Booking::create([
            'user_id' => $user->id,
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'starts_at' => '2026-12-01 00:00:00',
            'party_size' => 1,
            'total_price' => 50,
            'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    // ----- Endpoints admin protegidos por rol -----

    public function test_regular_user_cannot_create_tour(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $response = $this->apiJson('POST', '/api/v1/tours', [
            'data' => ['type' => 'tours', 'attributes' => [
                'title' => 'Hack', 'description' => 'x', 'price' => 1, 'max_capacity' => 1, 'location' => 'X',
            ]],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tours', ['title' => 'Hack']);
    }

    public function test_admin_can_create_tour(): void
    {
        Currency::create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ]);

        Passport::actingAs($this->makeUser('admin'));

        $response = $this->apiJson('POST', '/api/v1/tours', [
            'data' => ['type' => 'tours', 'attributes' => [
                'title' => 'Volcán Santa Ana', 'description' => 'Ascenso', 'price' => 65,
                'max_capacity' => 20, 'location' => 'Santa Ana', 'currency_code' => 'USD',
            ]],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('tours', ['title' => 'Volcán Santa Ana']);
    }

    public function test_regular_user_cannot_access_reports(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->apiGet('/api/v1/reports/overview')->assertForbidden();
    }

    public function test_regular_user_cannot_update_settings(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->apiJson('PATCH', '/api/v1/settings', [
            'data' => ['id' => 'current', 'type' => 'settings', 'attributes' => ['key' => 'app', 'value' => ['name' => 'Hacked']]],
        ])->assertForbidden();
    }

    public function test_regular_user_cannot_list_custom_inquiries(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->apiGet('/api/v1/custom-inquiries')->assertForbidden();
    }

    // ----- IDOR en bookings -----

    public function test_user_cannot_view_another_users_booking(): void
    {
        $owner = $this->makeUser('user');
        $booking = $this->makeBookingFor($owner);

        Passport::actingAs($this->makeUser('user')); // atacante

        $this->apiGet('/api/v1/bookings/'.$booking->id)->assertForbidden();
    }

    public function test_user_cannot_change_status_of_another_users_booking(): void
    {
        $owner = $this->makeUser('user');
        $booking = $this->makeBookingFor($owner);

        Passport::actingAs($this->makeUser('user')); // atacante

        $this->apiJson('PATCH', '/api/v1/bookings/'.$booking->id, [
            'data' => ['id' => (string) $booking->id, 'type' => 'bookings', 'attributes' => ['status' => Booking::STATUS_CONFIRMED]],
        ])->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function test_owner_can_view_own_booking(): void
    {
        $owner = $this->makeUser('user');
        $booking = $this->makeBookingFor($owner);

        Passport::actingAs($owner);

        $this->apiGet('/api/v1/bookings/'.$booking->id)->assertSuccessful();
    }

    public function test_admin_can_view_any_booking(): void
    {
        $owner = $this->makeUser('user');
        $booking = $this->makeBookingFor($owner);

        Passport::actingAs($this->makeUser('admin'));

        $this->apiGet('/api/v1/bookings/'.$booking->id)->assertSuccessful();
    }

    // ----- IDOR en payments -----

    public function test_user_cannot_view_another_users_payment(): void
    {
        $owner = $this->makeUser('user');
        $booking = $this->makeBookingFor($owner);

        $payment = Payment::create([
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'gateway' => 'manual',
            'method' => 'cash',
            'amount' => 50,
            'currency_code' => 'USD',
            'status' => 'pending',
        ]);

        Passport::actingAs($this->makeUser('user')); // atacante

        $this->apiGet('/api/v1/payments/'.$payment->id)->assertForbidden();
    }
}
