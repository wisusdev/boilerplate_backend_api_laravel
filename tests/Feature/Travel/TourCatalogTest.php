<?php

namespace Tests\Feature\Travel;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

    private function apiJson(string $method, string $uri, array $payload): TestResponse
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

    private function makeTour(array $attrs = []): Tour
    {
        return Tour::create(array_merge([
            'title' => 'Tour '.uniqid(),
            'description' => 'Desc',
            'price' => 100,
            'max_capacity' => 10,
            'location' => 'San Salvador',
            'currency_code' => 'USD',
            'is_active' => true,
        ], $attrs));
    }

    public function test_index_filtra_por_rango_de_precio(): void
    {
        $this->makeTour(['title' => 'Barato', 'price' => 30]);
        $this->makeTour(['title' => 'Medio', 'price' => 80]);
        $this->makeTour(['title' => 'Caro', 'price' => 150]);

        $response = $this->withHeaders($this->apiHeaders())
            ->get('/api/v1/tours?filter[priceMin]=50&filter[priceMax]=100');

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Medio']);
        $response->assertJsonMissing(['title' => 'Barato']);
        $response->assertJsonMissing(['title' => 'Caro']);
    }

    public function test_index_filtra_por_categoria(): void
    {
        $playa = TourCategory::create(['name' => 'Playa', 'is_active' => true]);
        $volcan = TourCategory::create(['name' => 'Volcanes', 'is_active' => true]);

        $this->makeTour(['title' => 'Surf', 'category_id' => $playa->id]);
        $this->makeTour(['title' => 'Cráter', 'category_id' => $volcan->id]);

        $response = $this->withHeaders($this->apiHeaders())
            ->get('/api/v1/tours?filter[categoryId]='.$playa->id);

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Surf']);
        $response->assertJsonMissing(['title' => 'Cráter']);
    }

    public function test_index_busca_por_titulo_o_ubicacion(): void
    {
        $this->makeTour(['title' => 'El Tunco Surf', 'location' => 'La Libertad']);
        $this->makeTour(['title' => 'Café tour', 'location' => 'Ahuachapán']);

        $response = $this->withHeaders($this->apiHeaders())
            ->get('/api/v1/tours?filter[search]=Tunco');

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'El Tunco Surf']);
        $response->assertJsonMissing(['title' => 'Café tour']);
    }

    public function test_index_ordena_por_precio_ascendente(): void
    {
        $this->makeTour(['title' => 'Caro', 'price' => 150]);
        $this->makeTour(['title' => 'Barato', 'price' => 30]);

        $response = $this->withHeaders($this->apiHeaders())
            ->get('/api/v1/tours?sort=price');

        $response->assertOk();
        $response->assertJsonPath('data.0.attributes.title', 'Barato');
    }

    public function test_index_respeta_el_tamano_de_pagina(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->makeTour(['title' => "Tour pag $i"]);
        }

        $response = $this->withHeaders($this->apiHeaders())
            ->get('/api/v1/tours?page[size]=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.total', 8);
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_index_rechaza_orden_no_permitido(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->get('/api/v1/tours?sort=secret_field')
            ->assertStatus(400);
    }

    public function test_store_creates_a_tour(): void
    {
        Currency::create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'rate_to_usd' => 1, 'is_default' => true, 'is_active' => true,
        ]);

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
