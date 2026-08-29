<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductReviewRequest;
use App\Http\Resources\ProductReviewResource;
use App\Models\Booking;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    /**
     * Listado público de reseñas aprobadas de un producto.
     * Filtros: filter[reviewableType]=tour|transport, filter[reviewableId]={id}
     */
    public function index(): AnonymousResourceCollection
    {
        $reviews = ProductReview::query()
            ->with('user')
            ->approved()
            ->allowedFilters(['reviewableType', 'reviewableId'])
            ->allowedSorts(['created_at', 'rating'])
            ->when(! request()->filled('sort'), fn ($q) => $q->latest())
            ->sparseFieldset()
            ->jsonPaginate();

        return ProductReviewResource::collection($reviews);
    }

    /**
     * Listado para moderación (admin): todas las reseñas, aprobadas o no.
     * Filtros: filter[isApproved]=0|1, filter[reviewableType]=tour|transport
     */
    public function adminIndex(): AnonymousResourceCollection
    {
        $reviews = ProductReview::query()
            ->with(['user', 'reviewable'])
            ->allowedFilters(['reviewableType', 'reviewableId', 'isApproved'])
            ->allowedSorts(['created_at', 'rating', 'is_approved'])
            ->when(! request()->filled('sort'), fn ($q) => $q->latest())
            ->jsonPaginate();

        return ProductReviewResource::collection($reviews);
    }

    /**
     * ¿El usuario autenticado puede reseñar este producto?
     * GET /product-reviews/eligibility?reviewable_type=tour&reviewable_id=1
     */
    public function eligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reviewable_type' => ['required', 'in:tour,transport'],
            'reviewable_id' => ['required', 'integer'],
        ]);

        $class = Booking::bookableClassFor($validated['reviewable_type']);
        $user = $request->user();

        $hasBooking = Booking::query()
            ->where('user_id', $user->id)
            ->where('bookable_type', $class)
            ->where('bookable_id', $validated['reviewable_id'])
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->exists();

        $hasReviewed = ProductReview::query()
            ->where('user_id', $user->id)
            ->where('reviewable_type', $class)
            ->where('reviewable_id', $validated['reviewable_id'])
            ->exists();

        return response()->json([
            'data' => [
                'type' => 'review_eligibility',
                'attributes' => [
                    'can_review' => $hasBooking && ! $hasReviewed,
                    'has_booking' => $hasBooking,
                    'has_reviewed' => $hasReviewed,
                ],
            ],
        ]);
    }

    public function store(ProductReviewRequest $request): JsonResponse
    {
        $attrs = $request->validated()['data']['attributes'];
        $user = $request->user();
        $class = Booking::bookableClassFor($attrs['reviewable_type']);

        // El producto debe existir.
        $product = $class::findOrFail($attrs['reviewable_id']);

        // Compra verificada: reserva no cancelada del usuario para este producto.
        $hasBooking = Booking::query()
            ->where('user_id', $user->id)
            ->where('bookable_type', $class)
            ->where('bookable_id', $product->id)
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->exists();

        if (! $hasBooking) {
            throw ValidationException::withMessages([
                'data.attributes.reviewable_id' => 'Debes tener una reserva de este producto para dejar una reseña.',
            ]);
        }

        // Una reseña por usuario y producto.
        $alreadyReviewed = ProductReview::query()
            ->where('user_id', $user->id)
            ->where('reviewable_type', $class)
            ->where('reviewable_id', $product->id)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'data.attributes.reviewable_id' => 'Ya has reseñado este producto.',
            ]);
        }

        $review = ProductReview::create([
            'user_id' => $user->id,
            'reviewable_type' => $class,
            'reviewable_id' => $product->id,
            'rating' => $attrs['rating'],
            'comment' => $attrs['comment'] ?? null,
            // Pendiente de moderación: el flujo de moderación ya existe
            // (product-reviews:moderate) y autoaprobar lo dejaba sin efecto.
            'is_approved' => false,
        ]);

        return ProductReviewResource::make($review->load('user'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ProductReviewRequest $request, ProductReview $productReview): ProductReviewResource
    {
        $user = $request->user();
        $isAdmin = $user->can('product-reviews:moderate');

        abort_unless($isAdmin || $productReview->user_id === $user->id, 403);

        $attrs = $request->validated()['data']['attributes'] ?? [];
        $update = [];

        // El autor puede editar su rating/comentario.
        if (array_key_exists('rating', $attrs)) {
            $update['rating'] = $attrs['rating'];
        }
        if (array_key_exists('comment', $attrs)) {
            $update['comment'] = $attrs['comment'];
        }

        // Solo admin: moderación y respuesta.
        if ($isAdmin) {
            if (array_key_exists('is_approved', $attrs)) {
                $update['is_approved'] = $attrs['is_approved'];
            }
            if (array_key_exists('admin_reply', $attrs)) {
                $update['admin_reply'] = $attrs['admin_reply'];
                $update['replied_at'] = $attrs['admin_reply'] ? now() : null;
            }
        }

        $productReview->update($update);

        return ProductReviewResource::make($productReview->fresh()->load('user'));
    }

    public function destroy(Request $request, ProductReview $productReview): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('product-reviews:moderate') || $productReview->user_id === $user->id, 403);

        $productReview->delete();

        return response()->json(null, 204);
    }
}
