<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Valida un cupón contra el contexto de una reserva y lo devuelve.
     * Lanza ValidationException (campo data.attributes.coupon_code) si no aplica.
     *
     * @param  array{user_id?:string|int|null, booking_type?:string|null, pax?:int, subtotal?:float}  $context
     */
    public function validate(string $code, array $context = []): Coupon
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $coupon) {
            $this->fail('El cupón no existe.');
        }

        if (! $coupon->isCurrentlyActive()) {
            $this->fail('El cupón no está vigente.');
        }

        $bookingType = $context['booking_type'] ?? null;
        if ($coupon->applies_to !== Coupon::SCOPE_ALL && $bookingType !== null && $coupon->applies_to !== $bookingType) {
            $this->fail('El cupón no aplica para este tipo de reserva.');
        }

        $pax = (int) ($context['pax'] ?? 0);
        if ($coupon->min_pax !== null && $pax > 0 && $pax < $coupon->min_pax) {
            $this->fail("El cupón requiere al menos {$coupon->min_pax} pasajeros.");
        }

        $subtotal = (float) ($context['subtotal'] ?? 0);
        if ($coupon->min_amount !== null && $subtotal > 0 && $subtotal < (float) $coupon->min_amount) {
            $this->fail('El monto de la reserva no alcanza el mínimo para este cupón.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            $this->fail('El cupón alcanzó su límite de usos.');
        }

        if ($coupon->per_user_limit !== null && ! empty($context['user_id'])) {
            $userUses = Booking::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $context['user_id'])
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->count();

            if ($userUses >= $coupon->per_user_limit) {
                $this->fail('Ya alcanzaste el límite de usos de este cupón.');
            }
        }

        return $coupon;
    }

    /**
     * Incrementa el contador de usos de forma atómica al canjear el cupón.
     */
    public function redeem(Coupon $coupon): void
    {
        Coupon::query()->whereKey($coupon->id)->increment('used_count');
    }

    /**
     * Libera un uso cuando una reserva con cupón se cancela.
     */
    public function release(Coupon $coupon): void
    {
        Coupon::query()->whereKey($coupon->id)->where('used_count', '>', 0)->decrement('used_count');
    }

    /**
     * @return never
     */
    private function fail(string $message): void
    {
        throw ValidationException::withMessages([
            'data.attributes.coupon_code' => $message,
        ]);
    }
}
