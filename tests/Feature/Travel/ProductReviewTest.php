<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Reseñas de producto: compra verificada, unicidad por usuario/producto,
 * visibilidad pública (solo aprobadas) y moderación admin.
 */
class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'super-admin', 'user'] as $role) {
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

    private function makeUser(string $role = 'user'): User
    {
        $user = User::create([
            'username'   => 'u' . uniqid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => uniqid() . '@example.com',
            'password'   => bcrypt('password123'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeTour(): Tour
    {
        return Tour::create([
            'title' => 'Volcán Santa Ana', 'description' => 'x', 'price' => 50,
            'max_capacity' => 20, 'location' => 'Santa Ana', 'currency_code' => 'USD',
        ]);
    }

    private function bookingFor(User $user, Tour $tour, string $status = 'confirmed'): Booking
    {
        return Booking::create([
            'user_id' => $user->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(3), 'party_size' => 2, 'total_price' => 100,
            'currency_code' => 'USD', 'status' => $status,
        ]);
    }

    private function reviewPayload(int $tourId, int $rating = 5, ?string $comment = 'Excelente'): array
    {
        return ['data' => ['type' => 'product_reviews', 'attributes' => [
            'reviewable_type' => 'tour', 'reviewable_id' => $tourId, 'rating' => $rating, 'comment' => $comment,
        ]]];
    }

    public function test_verified_purchaser_can_create_review(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->bookingFor($user, $tour);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/product-reviews', $this->reviewPayload($tour->id))
            ->assertStatus(201);

        $this->assertDatabaseHas('product_reviews', [
            'user_id' => $user->id, 'reviewable_id' => $tour->id, 'rating' => 5, 'is_approved' => true,
        ]);
    }

    public function test_review_requires_a_booking(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/product-reviews', $this->reviewPayload($tour->id))
            ->assertStatus(422);
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_user_cannot_review_same_product_twice(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->bookingFor($user, $tour);
        Passport::actingAs($user);

        $this->apiJson('POST', '/api/v1/product-reviews', $this->reviewPayload($tour->id))->assertStatus(201);
        $this->apiJson('POST', '/api/v1/product-reviews', $this->reviewPayload($tour->id, 3))->assertStatus(422);
        $this->assertDatabaseCount('product_reviews', 1);
    }

    public function test_public_index_returns_only_approved(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        ProductReview::create(['user_id' => $user->id, 'reviewable_type' => Tour::class, 'reviewable_id' => $tour->id, 'rating' => 5, 'is_approved' => true]);
        $other = $this->makeUser();
        ProductReview::create(['user_id' => $other->id, 'reviewable_type' => Tour::class, 'reviewable_id' => $tour->id, 'rating' => 1, 'is_approved' => false]);

        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/product-reviews?filter[reviewableType]=tour&filter[reviewableId]=' . $tour->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_tour_resource_exposes_average_rating(): void
    {
        $tour = $this->makeTour();
        foreach ([4, 5] as $r) {
            $u = $this->makeUser();
            ProductReview::create(['user_id' => $u->id, 'reviewable_type' => Tour::class, 'reviewable_id' => $tour->id, 'rating' => $r, 'is_approved' => true]);
        }

        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/tours/' . $tour->id)
            ->assertOk()
            ->assertJsonPath('data.attributes.average_rating', 4.5)
            ->assertJsonPath('data.attributes.reviews_count', 2);
    }

    public function test_admin_can_moderate_and_reply(): void
    {
        $author = $this->makeUser();
        $tour = $this->makeTour();
        $review = ProductReview::create(['user_id' => $author->id, 'reviewable_type' => Tour::class, 'reviewable_id' => $tour->id, 'rating' => 4, 'is_approved' => false]);

        Passport::actingAs($this->makeUser('admin'));

        // Admin index ve también las no aprobadas.
        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/product-reviews/admin?filter[isApproved]=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Aprobar + responder.
        $this->apiJson('PATCH', '/api/v1/product-reviews/' . $review->id, [
            'data' => ['type' => 'product_reviews', 'id' => (string) $review->id, 'attributes' => ['is_approved' => true, 'admin_reply' => 'Gracias!']],
        ])->assertOk();

        $this->assertDatabaseHas('product_reviews', ['id' => $review->id, 'is_approved' => true, 'admin_reply' => 'Gracias!']);
    }

    public function test_admin_index_requires_admin_role(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/product-reviews/admin')
            ->assertForbidden();
    }
}
