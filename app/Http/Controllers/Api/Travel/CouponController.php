<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(private readonly CouponService $couponService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $coupons = Coupon::query()
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'LIKE', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->latest()
            ->jsonPaginate();

        return CouponResource::collection($coupons);
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated()['data']['attributes']);

        return CouponResource::make($coupon->fresh())
            ->response()
            ->setStatusCode(201);
    }

    public function update(CouponRequest $request, Coupon $coupon): CouponResource
    {
        $coupon->update($request->validated()['data']['attributes']);

        return CouponResource::make($coupon->fresh());
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(null, 204);
    }

    /**
     * Previsualiza la validez y el descuento de un cupón para una reserva.
     * No aplica nada; el descuento definitivo se recalcula al crear la reserva.
     *
     * POST /coupons/validate  { data.attributes: { code, booking_type?, pax?, amount? } }
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', $request->all());

        $data = validator($attrs, [
            'code' => ['required', 'string', 'max:60'],
            'booking_type' => ['sometimes', 'nullable', 'string', 'in:tour,transport'],
            'pax' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ])->validate();

        $subtotal = (float) ($data['amount'] ?? 0);

        try {
            $coupon = $this->couponService->validate($data['code'], [
                'user_id' => $request->user()?->id,
                'booking_type' => $data['booking_type'] ?? null,
                'pax' => (int) ($data['pax'] ?? 0),
                'subtotal' => $subtotal,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'data' => [
                    'type' => 'coupon_validation',
                    'attributes' => [
                        'valid' => false,
                        'message' => $e->validator->errors()->first('data.attributes.coupon_code'),
                    ],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'type' => 'coupon_validation',
                'attributes' => [
                    'valid' => true,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'discount' => $subtotal > 0 ? $coupon->discountFor($subtotal) : null,
                ],
            ],
        ]);
    }
}
