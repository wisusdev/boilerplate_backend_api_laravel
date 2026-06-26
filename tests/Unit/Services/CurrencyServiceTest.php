<?php

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CurrencyService();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createCurrency(string $code, float $rateToUsd, bool $isDefault = false): Currency
    {
        return Currency::create([
            'code'        => $code,
            'name'        => $code . ' Currency',
            'symbol'      => $code[0],
            'rate_to_usd' => $rateToUsd,
            'is_default'  => $isDefault,
            'is_active'   => true,
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_default_currency_returns_the_marked_default(): void
    {
        $this->createCurrency('EUR', 0.92, false);
        $this->createCurrency('USD', 1.0, true);

        $currency = $this->service->defaultCurrency();

        $this->assertEquals('USD', $currency->code);
    }

    public function test_convert_usd_to_eur(): void
    {
        $this->createCurrency('USD', 1.0);
        $this->createCurrency('EUR', 0.92);

        $result = $this->service->convert(100.0, 'USD', 'EUR');

        $this->assertEquals(92.0, $result);
    }

    public function test_convert_eur_to_usd(): void
    {
        $this->createCurrency('USD', 1.0);
        $this->createCurrency('EUR', 0.92);

        // 92 EUR → 92/0.92 = 100 USD
        $result = $this->service->convert(92.0, 'EUR', 'USD');

        $this->assertEquals(100.0, $result);
    }

    public function test_convert_returns_same_amount_for_identical_currency(): void
    {
        $this->createCurrency('USD', 1.0);

        $result = $this->service->convert(75.50, 'USD', 'USD');

        $this->assertEquals(75.50, $result);
    }

    public function test_convert_cross_currency_via_usd(): void
    {
        $this->createCurrency('EUR', 0.92);
        $this->createCurrency('GBP', 0.80);

        // 92 EUR → 92/0.92 = 100 USD → 100 * 0.80 = 80 GBP
        $result = $this->service->convert(92.0, 'EUR', 'GBP');

        $this->assertEquals(80.0, $result);
    }

    public function test_convert_is_case_insensitive(): void
    {
        $this->createCurrency('USD', 1.0);
        $this->createCurrency('EUR', 0.92);

        $result = $this->service->convert(100.0, 'usd', 'eur');

        $this->assertEquals(92.0, $result);
    }

    public function test_convert_rounds_to_two_decimal_places(): void
    {
        $this->createCurrency('USD', 1.0);
        $this->createCurrency('JPY', 157.35);

        $result = $this->service->convert(1.0, 'USD', 'JPY');

        $this->assertEquals(round(157.35, 2), $result);
    }

    public function test_default_currency_throws_when_none_is_marked_default(): void
    {
        $this->createCurrency('USD', 1.0, false);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->defaultCurrency();
    }

    public function test_marking_a_currency_default_unsets_the_previous_default(): void
    {
        $usd = $this->createCurrency('USD', 1.0, true);
        $eur = $this->createCurrency('EUR', 0.92, false);

        // Marcar EUR como predeterminada debe desmarcar USD automáticamente.
        $eur->update(['is_default' => true]);

        $this->assertFalse($usd->fresh()->is_default);
        $this->assertTrue($eur->fresh()->is_default);
        $this->assertSame(1, Currency::where('is_default', true)->count());
    }
}
