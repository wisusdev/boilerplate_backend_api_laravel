<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Política de reserva GLOBAL (moneda, antelación y cancelación) que aplica por
 * igual a todos los tours y vehículos.
 */
class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'username' => 'u'.uniqid(), 'first_name' => 'P', 'last_name' => 'Q',
            'email' => uniqid().'@example.com', 'password' => bcrypt('password123'),
        ]);
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'api');
        $a = $this->user();
        $a->assignRole('admin');

        return $a;
    }

    private function setCancellationHours(int $hours): void
    {
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode(['booking_cancellation_hours' => $hours])]);
        SiteSettings::flush();
    }

    private function pendingBooking(User $owner, string $startsAt): Booking
    {
        $tour = Tour::create([
            'title' => 'T', 'description' => 'x', 'price' => 50,
            'max_capacity' => 20, 'location' => 'SV',
        ]);

        return Booking::create([
            'user_id' => $owner->id,
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'starts_at' => $startsAt,
            'party_size' => 1,
            'total_price' => 50,
            'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function test_customer_cannot_cancel_within_global_window(): void
    {
        $this->setCancellationHours(24);
        $owner = $this->user();
        Passport::actingAs($owner);

        // Inicio en 10 horas: dentro de la ventana de 24h → no se puede cancelar.
        $booking = $this->pendingBooking($owner, now()->addHours(10)->toDateTimeString());

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertStatus(422);

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_customer_can_cancel_before_the_window(): void
    {
        $this->setCancellationHours(24);
        $owner = $this->user();
        Passport::actingAs($owner);

        // Inicio en 3 días: fuera de la ventana → sí se puede cancelar.
        $booking = $this->pendingBooking($owner, now()->addDays(3)->toDateTimeString());

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertSuccessful();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_admin_can_cancel_within_the_window(): void
    {
        $this->setCancellationHours(24);
        $owner = $this->user();
        $booking = $this->pendingBooking($owner, now()->addHours(2)->toDateTimeString());

        Passport::actingAs($this->admin());
        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertSuccessful();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }
}
