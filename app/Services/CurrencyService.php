<?php

namespace App\Services;

use App\Models\Currency;

class CurrencyService
{
    public function defaultCurrency(): Currency
    {
        return Currency::query()->where('is_default', true)->firstOrFail();
    }

    public function convert(float $amount, string $fromCode, string $toCode): float
    {
        $from = Currency::query()->where('code', strtoupper($fromCode))->firstOrFail();
        $to = Currency::query()->where('code', strtoupper($toCode))->firstOrFail();

        $usdAmount = $amount / (float) $from->rate_to_usd;

        return round($usdAmount * (float) $to->rate_to_usd, 2);
    }
}
