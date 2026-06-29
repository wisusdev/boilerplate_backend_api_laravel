<?php

namespace Tests\Feature\Travel;

use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TransportAndInquiryTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }

    private function apiJson(string $method, string $uri, array $payload): \Illuminate\Testing\TestResponse
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

    public function test_transport_booking_store_creates_booking(): void
    {
        $user = User::create([
            'username' => 'driver1',
            'first_name' => 'Driver',
            'last_name' => 'One',
            'email' => 'driver1@example.com',
            'password' => bcrypt('password123'),
        ]);

        Passport::actingAs($user);

        $vehicle = TransportVehicle::create([
            'title' => 'Van Deluxe',
            'vehicle_type' => 'van',
            'description' => 'Air conditioned',
            'location' => 'San Salvador',
            'hourly_rate' => 25,
            'daily_rate' => 120,
            'capacity' => 12,
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $response = $this->apiJson('POST', '/api/v1/bookings', [
            'data' => [
                'type' => 'bookings',
                'attributes' => [
                    'booking_type' => 'transport',
                    'transport_vehicle_id' => $vehicle->id,
                    'pickup_at' => '2026-06-10 08:00:00',
                    'dropoff_at' => '2026-06-10 12:00:00',
                    'pickup_location' => 'Airport',
                    'dropoff_location' => 'Hotel',
                    'rental_type' => 'hourly',
                    'quantity' => 1,
                    'currency_code' => 'USD',
                ],
            ],
        ]);

        $response->assertSuccessful();
        // La reserva vive en la tabla unificada `bookings` (polimórfica)...
        $this->assertDatabaseHas('bookings', [
            'bookable_type' => TransportVehicle::class,
            'bookable_id'   => $vehicle->id,
            'user_id'       => $user->id,
        ]);
        // ...y los campos específicos en la tabla de extensión 1:1.
        $this->assertDatabaseHas('transport_booking_details', [
            'pickup_location'  => 'Airport',
            'dropoff_location' => 'Hotel',
            'rental_type'      => 'hourly',
        ]);
    }

    public function test_custom_inquiry_store_creates_record(): void
    {
        \App\Models\Currency::create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ]);

        $response = $this->apiJson('POST', '/api/v1/custom-inquiries', [
            'data' => [
                'type' => 'custom_inquiries',
                'attributes' => [
                    'preferred_destinations' => ['Ataco', 'Apaneca'],
                    'travel_start_date' => '2026-07-01',
                    'travel_end_date' => '2026-07-05',
                    'budget_min' => 500,
                    'budget_max' => 1200,
                    'travelers_count' => 4,
                    'currency_code' => 'USD',
                    'message' => 'Need a family trip plan',
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('custom_inquiries', [
            'travelers_count' => 4,
            'message' => 'Need a family trip plan',
        ]);
    }

    public function test_inquiry_notifica_a_los_correos_configurados(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\Currency::create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ]);

        // Campo dedicado de notificaciones: admite uno o varios correos.
        \App\Models\Setting::create([
            'key'   => 'app',
            'value' => json_encode([
                'contact_email' => 'publico@empresa.com',
                'inquiry_notification_emails' => 'ventas@empresa.com, reservas@empresa.com',
            ]),
        ]);

        $this->apiJson('POST', '/api/v1/custom-inquiries', [
            'data' => [
                'type' => 'custom_inquiries',
                'attributes' => ['preferred_destinations' => ['Ataco'], 'currency_code' => 'USD'],
            ],
        ])->assertCreated();

        foreach (['ventas@empresa.com', 'reservas@empresa.com'] as $email) {
            \Illuminate\Support\Facades\Notification::assertSentTo(
                new \Illuminate\Notifications\AnonymousNotifiable,
                \App\Notifications\AdminAlertNotification::class,
                fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $email
            );
        }
    }

    public function test_inquiry_usa_correo_publico_si_no_hay_dedicados(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\Currency::create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ]);

        \App\Models\Setting::create([
            'key'   => 'app',
            'value' => json_encode(['contact_email' => 'publico@empresa.com']),
        ]);

        $this->apiJson('POST', '/api/v1/custom-inquiries', [
            'data' => [
                'type' => 'custom_inquiries',
                'attributes' => ['preferred_destinations' => ['Ataco'], 'currency_code' => 'USD'],
            ],
        ])->assertCreated();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            \App\Notifications\AdminAlertNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'publico@empresa.com'
        );
    }
}