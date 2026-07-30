<?php

namespace Tests\Feature\Travel;

use App\Models\Coupon;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Siembra el catálogo real de permisos y roles (admin recibe todos).
        $this->seed([\Database\Seeders\PermissionSeeder::class, \Database\Seeders\RoleSeeder::class]);
    }

    private function makeUser(?string $role = null): User
    {
        $user = User::create([
            'username' => 'u'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password123'),
        ]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function tour(): Tour
    {
        return Tour::create([
            'title' => 'Coupon Tour', 'description' => 'x', 'price' => 100,
            'max_capacity' => 20, 'location' => 'SV', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    public function test_admin_can_create_coupon(): void
    {
        Passport::actingAs($this->makeUser('admin'));

        $response = $this->apiJson('POST', '/api/v1/coupons', [
            'data' => [
                'type' => 'coupons',
                'attributes' => [
                    'code' => 'welcome10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'all',
                ],
            ],
        ]);

        $response->assertCreated();
        // El código se normaliza a mayúsculas.
        $this->assertDatabaseHas('coupons', ['code' => 'WELCOME10', 'value' => 10]);
    }

    public function test_non_admin_cannot_create_coupon(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->apiJson('POST', '/api/v1/coupons', [
            'data' => ['type' => 'coupons', 'attributes' => ['code' => 'X', 'type' => 'fixed', 'value' => 5]],
        ])->assertForbidden();
    }

    public function test_percentage_over_100_is_rejected(): void
    {
        Passport::actingAs($this->makeUser('admin'));

        $this->apiJson('POST', '/api/v1/coupons', [
            'data' => ['type' => 'coupons', 'attributes' => ['code' => 'BIG', 'type' => 'percentage', 'value' => 150]],
        ])->assertJsonApiValidationErrors('data.attributes.value');
    }

    public function test_validate_endpoint_returns_discount(): void
    {
        Passport::actingAs($this->makeUser('user'));
        Coupon::create(['code' => 'SAVE20', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'all', 'is_active' => true]);

        $response = $this->apiJson('POST', '/api/v1/coupons/validate', [
            'data' => ['attributes' => ['code' => 'SAVE20', 'booking_type' => 'tour', 'pax' => 2, 'amount' => 200]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.attributes.valid', true);
        $response->assertJsonPath('data.attributes.discount', 40);
    }

    public function test_validate_endpoint_reports_invalid(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->apiJson('POST', '/api/v1/coupons/validate', [
            'data' => ['attributes' => ['code' => 'GHOST', 'amount' => 100]],
        ])->assertOk()->assertJsonPath('data.attributes.valid', false);
    }

    public function test_booking_with_coupon_applies_discount_and_redeems(): void
    {
        Passport::actingAs($this->makeUser('user'));
        $tour = $this->tour();
        $coupon = Coupon::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'tour', 'is_active' => true]);

        $response = $this->apiJson('POST', '/api/v1/bookings', [
            'data' => [
                'type' => 'bookings',
                'attributes' => [
                    'booking_type' => 'tour',
                    'tour_id' => $tour->id,
                    'booking_date' => now()->addDays(5)->toDateString(),
                    'pax_count' => 2,
                    'coupon_code' => 'TEN',
                ],
            ],
        ]);

        $response->assertSuccessful();
        // 100 * 2 = 200; 10% => descuento 20; total 180.
        $response->assertJsonPath('data.attributes.total_price', '180.00');
        $response->assertJsonPath('data.attributes.discount_amount', '20.00');
        $response->assertJsonPath('data.attributes.coupon_code', 'TEN');
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_coupon_discount_flows_into_invoice_on_confirm(): void
    {
        Passport::actingAs($this->makeUser('user'));
        $tour = $this->tour();
        Coupon::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'tour', 'is_active' => true]);

        // Reserva con cupón: 100 * 2 - 10% = 180.
        $booking = $this->apiJson('POST', '/api/v1/bookings', [
            'data' => ['type' => 'bookings', 'attributes' => [
                'booking_type' => 'tour', 'tour_id' => $tour->id,
                'booking_date' => now()->addDays(5)->toDateString(), 'pax_count' => 2, 'coupon_code' => 'TEN',
            ]],
        ])->assertSuccessful()->json('data.id');

        // El dueño confirma la reserva → se genera la factura con el total descontado.
        $this->apiJson('PATCH', '/api/v1/bookings/'.$booking, [
            'data' => ['id' => (string) $booking, 'type' => 'bookings', 'attributes' => ['status' => 'confirmed']],
        ])->assertOk();

        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking,
            'amount' => 180,
        ]);
    }

    public function test_cancelling_coupon_booking_releases_use(): void
    {
        Passport::actingAs($this->makeUser('user'));
        $tour = $this->tour();
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 10, 'applies_to' => 'tour', 'is_active' => true]);

        $booking = $this->apiJson('POST', '/api/v1/bookings', [
            'data' => ['type' => 'bookings', 'attributes' => [
                'booking_type' => 'tour', 'tour_id' => $tour->id,
                'booking_date' => now()->addDays(5)->toDateString(), 'pax_count' => 2, 'coupon_code' => 'ONCE',
            ]],
        ])->assertSuccessful()->json('data.id');

        $this->assertSame(1, (int) $coupon->fresh()->used_count);

        // Cancelar libera el uso.
        $this->call('POST', '/api/v1/bookings/'.$booking.'/cancel', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ])->assertOk();

        $this->assertSame(0, (int) $coupon->fresh()->used_count);
    }

    public function test_booking_rejects_coupon_below_min_pax(): void
    {
        Passport::actingAs($this->makeUser('user'));
        $tour = $this->tour();
        Coupon::create(['code' => 'GRP', 'type' => 'fixed', 'value' => 20, 'min_pax' => 5, 'applies_to' => 'tour', 'is_active' => true]);

        $this->apiJson('POST', '/api/v1/bookings', [
            'data' => [
                'type' => 'bookings',
                'attributes' => [
                    'booking_type' => 'tour',
                    'tour_id' => $tour->id,
                    'booking_date' => now()->addDays(5)->toDateString(),
                    'pax_count' => 2,
                    'coupon_code' => 'GRP',
                ],
            ],
        ])->assertJsonApiValidationErrors('data.attributes.coupon_code');
    }
}
