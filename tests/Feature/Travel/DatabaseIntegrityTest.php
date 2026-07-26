<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Cubre las refactorizaciones de integridad de la base de datos:
 *  - tours.category normalizado a category_id (FK a tour_categories).
 *  - currency_code validado contra currencies.code en catálogos.
 *  - booking_type derivado de bookable_type (sin columna).
 *  - invoices con currency_code (snapshot) y soft delete.
 */
class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'user'] as $role) {
            Role::findOrCreate($role, 'api');
        }
    }

    private function apiJson(string $method, string $uri, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode($payload));
    }

    private function admin(): User
    {
        $user = User::create([
            'username'   => 'admin_integrity',
            'first_name' => 'Admin',
            'last_name'  => 'Integrity',
            'email'      => 'admin.integrity@example.com',
            'password'   => bcrypt('password123'),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function usd(): Currency
    {
        return Currency::create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ]);
    }

    // ─── tours.category_id ──────────────────────────────────────────────────────

    public function test_tour_exposes_category_name_through_relationship(): void
    {
        $category = TourCategory::create(['name' => 'Volcanes', 'color' => '#184ca0', 'is_active' => true]);
        $tour = Tour::create([
            'title' => 'Santa Ana', 'description' => 'x', 'price' => 65,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD',
            'category_id' => $category->id, 'is_active' => true,
        ]);

        $response = $this->apiJson('GET', '/api/v1/tours/' . $tour->id);

        $response->assertOk()
            ->assertJsonPath('data.attributes.category_id', $category->id)
            ->assertJsonPath('data.attributes.category', 'Volcanes');
    }

    public function test_tour_uses_global_currency_ignoring_sent_value(): void
    {
        $this->usd();
        TourCategory::create(['name' => 'Playa', 'is_active' => true]);
        Passport::actingAs($this->admin());

        // La moneda es GLOBAL del sitio: cualquier currency_code enviado se ignora.
        $this->apiJson('POST', '/api/v1/tours', [
            'data' => ['type' => 'tours', 'attributes' => [
                'title' => 'Currency test', 'description' => 'x', 'price' => 10,
                'max_capacity' => 5, 'location' => 'X', 'currency_code' => 'XXX',
            ]],
        ])->assertCreated()->assertJsonPath('data.attributes.currency_code', 'USD');
    }

    public function test_creating_tour_with_unknown_category_is_rejected(): void
    {
        $this->usd();
        Passport::actingAs($this->admin());

        $this->apiJson('POST', '/api/v1/tours', [
            'data' => ['type' => 'tours', 'attributes' => [
                'title' => 'Bad category', 'description' => 'x', 'price' => 10,
                'max_capacity' => 5, 'location' => 'X', 'currency_code' => 'USD',
                'category_id' => 999999,
            ]],
        ])->assertJsonApiValidationErrors('data.attributes.category_id');
    }

    // ─── booking_type derivado ──────────────────────────────────────────────────

    public function test_booking_type_is_derived_and_not_a_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('bookings', 'booking_type'),
            'booking_type no debe existir como columna; se deriva de bookable_type.'
        );

        $user = $this->admin();
        $tour = Tour::create([
            'title' => 'Derive', 'description' => 'x', 'price' => 20,
            'max_capacity' => 5, 'location' => 'X', 'currency_code' => 'USD', 'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $user->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => '2099-01-01 00:00:00', 'party_size' => 1, 'total_price' => 20,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        $this->assertSame(Booking::TYPE_TOUR, $booking->booking_type);
        $this->assertSame(Tour::class, Booking::bookableClassFor(Booking::TYPE_TOUR));
    }

    // ─── invoices: snapshot de moneda + soft delete ─────────────────────────────

    public function test_confirmed_booking_invoice_snapshots_currency(): void
    {
        $user = $this->admin();
        Passport::actingAs($user);

        $tour = Tour::create([
            'title' => 'Invoice currency', 'description' => 'x', 'price' => 40,
            'max_capacity' => 10, 'location' => 'X', 'currency_code' => 'USD', 'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $user->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => '2099-02-01 00:00:00', 'party_size' => 1, 'total_price' => 40,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        $this->apiJson('PATCH', '/api/v1/bookings/' . $booking->id, [
            'data' => ['id' => (string) $booking->id, 'type' => 'bookings', 'attributes' => [
                'status' => Booking::STATUS_CONFIRMED,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('invoices', [
            'booking_id'    => $booking->id,
            'currency_code' => 'USD',
        ]);
    }

    public function test_invoices_are_soft_deleted(): void
    {
        $user = $this->admin();
        $tour = Tour::create([
            'title' => 'Soft delete', 'description' => 'x', 'price' => 30,
            'max_capacity' => 5, 'location' => 'X', 'currency_code' => 'USD', 'is_active' => true,
        ]);
        $booking = Booking::create([
            'user_id' => $user->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => '2099-03-01 00:00:00', 'party_size' => 1, 'total_price' => 30,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);
        $invoice = Invoice::create([
            'booking_id' => $booking->id, 'amount' => 30, 'currency_code' => 'USD', 'status' => 'pending',
        ]);

        $invoice->delete();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }
}
