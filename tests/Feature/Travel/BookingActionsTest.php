<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\BookingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BookingActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'super-admin', 'user'] as $r) {
            Role::findOrCreate($r, 'api');
        }
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'username'   => 'u' . uniqid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'password'   => bcrypt('password123'),
        ]);
    }

    private function makeTourBooking(User $user, string $status = Booking::STATUS_PENDING): Booking
    {
        $tour = Tour::create([
            'title' => 'Tour Test', 'description' => 'd', 'price' => 50, 'max_capacity' => 10,
            'location' => 'SV', 'currency_code' => 'USD', 'is_active' => true,
        ]);

        return Booking::create([
            'bookable_type' => Tour::class,
            'bookable_id'   => $tour->id,
            'user_id'       => $user->id,
            'starts_at'     => now()->addDays(3),
            'party_size'    => 2,
            'total_price'   => 100,
            'currency_code' => 'USD',
            'status'        => $status,
        ]);
    }

    public function test_cancelar_reserva_pendiente_cambia_estado_y_notifica(): void
    {
        Notification::fake();
        $user = $this->makeUser('owner@example.com');
        $booking = $this->makeTourBooking($user);
        Passport::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        Notification::assertSentTo($user, BookingNotification::class);
    }

    public function test_no_se_puede_cancelar_reserva_confirmada(): void
    {
        $user = $this->makeUser('owner2@example.com');
        $booking = $this->makeTourBooking($user, Booking::STATUS_CONFIRMED);
        Passport::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertStatus(422);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_no_se_puede_cancelar_reserva_ajena(): void
    {
        $owner = $this->makeUser('owner3@example.com');
        $other = $this->makeUser('other@example.com');
        $booking = $this->makeTourBooking($owner);
        Passport::actingAs($other);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertStatus(403);
    }

    public function test_reagendar_tour_cambia_fecha(): void
    {
        Notification::fake();
        $user = $this->makeUser('owner4@example.com');
        $booking = $this->makeTourBooking($user);
        Passport::actingAs($user);

        $newDate = now()->addDays(10)->toDateString();
        $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", ['date' => $newDate])->assertOk();

        $this->assertEquals($newDate, $booking->fresh()->starts_at->toDateString());
        Notification::assertSentTo($user, BookingNotification::class);
    }

    public function test_enviar_mensaje_guarda_y_notifica_admins(): void
    {
        Notification::fake();
        $admin = $this->makeUser('admin@example.com');
        $admin->assignRole('admin');
        $user = $this->makeUser('owner5@example.com');
        $booking = $this->makeTourBooking($user);
        Passport::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/messages", ['message' => 'Necesito cambiar la hora'])
            ->assertOk();

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $booking->id,
            'message'    => 'Necesito cambiar la hora',
        ]);
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            AdminAlertNotification::class,
            fn ($n, $ch, $notifiable) => $notifiable->routes['mail'] === 'admin@example.com'
        );
    }

    public function test_enviar_mensaje_no_falla_si_no_existe_rol_super_admin(): void
    {
        // En algunos entornos solo existe el rol 'admin' (no 'super-admin').
        Role::query()->where('name', 'super-admin')->delete();

        $user = $this->makeUser('owner7@example.com');
        $booking = $this->makeTourBooking($user);
        Passport::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/messages", ['message' => 'Hola'])
            ->assertOk();
    }

    public function test_descargar_comprobante_pdf(): void
    {
        $user = $this->makeUser('owner6@example.com');
        $booking = $this->makeTourBooking($user);
        Passport::actingAs($user);

        $response = $this->get("/api/v1/bookings/{$booking->id}/receipt");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }
}
