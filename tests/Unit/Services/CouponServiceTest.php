<?php

namespace Tests\Unit\Services;

use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CouponService::class);
    }

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE10', 'type' => Coupon::TYPE_PERCENTAGE, 'value' => 10,
            'applies_to' => 'all', 'is_active' => true,
        ], $overrides));
    }

    public function test_percentage_discount_is_computed(): void
    {
        $coupon = $this->coupon(['type' => 'percentage', 'value' => 10]);
        $this->assertSame(15.0, $coupon->discountFor(150));
    }

    public function test_percentage_discount_is_capped_by_max_discount(): void
    {
        $coupon = $this->coupon(['type' => 'percentage', 'value' => 50, 'max_discount' => 20]);
        $this->assertSame(20.0, $coupon->discountFor(100));
    }

    public function test_fixed_discount_never_exceeds_subtotal(): void
    {
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 80]);
        $this->assertSame(50.0, $coupon->discountFor(50));
    }

    public function test_validate_passes_for_valid_coupon(): void
    {
        $this->coupon(['code' => 'OK']);
        $resolved = $this->service->validate('ok', ['booking_type' => 'tour', 'pax' => 2, 'subtotal' => 100]);
        $this->assertSame('OK', $resolved->code);
    }

    public function test_validate_rejects_unknown_code(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->validate('NOPE', []);
    }

    public function test_validate_rejects_expired_coupon(): void
    {
        $this->coupon(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $this->expectException(ValidationException::class);
        $this->service->validate('OLD', []);
    }

    public function test_validate_rejects_wrong_scope(): void
    {
        $this->coupon(['code' => 'ONLYTRANSPORT', 'applies_to' => 'transport']);
        $this->expectException(ValidationException::class);
        $this->service->validate('ONLYTRANSPORT', ['booking_type' => 'tour']);
    }

    public function test_validate_rejects_below_min_pax(): void
    {
        $this->coupon(['code' => 'GROUP', 'min_pax' => 5]);
        $this->expectException(ValidationException::class);
        $this->service->validate('GROUP', ['pax' => 3]);
    }

    public function test_validate_rejects_when_usage_limit_reached(): void
    {
        $this->coupon(['code' => 'LIMITED', 'usage_limit' => 2, 'used_count' => 2]);
        $this->expectException(ValidationException::class);
        $this->service->validate('LIMITED', []);
    }

    public function test_redeem_increments_used_count(): void
    {
        $coupon = $this->coupon(['used_count' => 0]);
        $this->service->redeem($coupon);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_redeem_no_supera_el_usage_limit_aunque_se_llame_sin_pasar_por_validate(): void
    {
        // Simula el desenlace de la condición de carrera que este fix cierra:
        // dos solicitudes pasan validate() con used_count aún por debajo del
        // límite y ambas intentan canjear. redeem() debe fallar cerrado en la
        // segunda en vez de dejar used_count por encima de usage_limit.
        $coupon = $this->coupon(['code' => 'RACE', 'usage_limit' => 1, 'used_count' => 0]);

        $this->service->redeem($coupon);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);

        $this->expectException(ValidationException::class);
        $this->service->redeem($coupon);
    }
}
