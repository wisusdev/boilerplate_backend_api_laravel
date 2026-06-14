<?php

namespace Tests\Feature\Travel;

use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TourCatalogTest extends TestCase
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

    public function test_index_returns_only_active_tours(): void
    {
        Tour::create([
            'title' => 'Active tour',
            'description' => 'Active',
            'price' => 100,
            'max_capacity' => 10,
            'location' => 'San Salvador',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        Tour::create([
            'title' => 'Inactive tour',
            'description' => 'Inactive',
            'price' => 100,
            'max_capacity' => 10,
            'location' => 'San Salvador',
            'currency_code' => 'USD',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->apiHeaders())->get('/api/v1/tours');

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Active tour']);
        $response->assertJsonMissing(['title' => 'Inactive tour']);
    }

    public function test_store_creates_a_tour(): void
    {
        Role::findOrCreate('admin', 'api');

        $user = User::create([
            'username' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');

        Passport::actingAs($user);

        $response = $this->apiJson('POST', '/api/v1/tours', [
            'data' => [
                'type' => 'tours',
                'attributes' => [
                    'title' => 'Volcano route',
                    'description' => 'One day excursion',
                    'price' => 85.50,
                    'max_capacity' => 15,
                    'location' => 'Santa Ana',
                    'currency_code' => 'USD',
                    'is_active' => true,
                    'itinerary' => [['time' => '08:00', 'title' => 'Pickup']],
                    'highlights' => ['Views', 'Guide'],
                    'gallery' => ['https://example.com/photo.jpg'],
                    'faqs' => [['question' => 'Bring water?', 'answer' => 'Yes']],
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tours', [
            'title' => 'Volcano route',
            'location' => 'Santa Ana',
        ]);
    }
}