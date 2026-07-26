<?php

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function setPaymentGateway(array $data): void
    {
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode($data)]);
        SiteSettings::flush();
    }

    private function setApp(array $data): void
    {
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode($data)]);
        SiteSettings::flush();
    }

    public function test_currency_defaults_to_usd_when_unset(): void
    {
        $this->assertSame('USD', SiteSettings::currency());
    }

    public function test_currency_prefers_default_currency_then_currency(): void
    {
        $this->setPaymentGateway(['default_currency' => 'EUR', 'currency' => 'GBP']);
        $this->assertSame('EUR', SiteSettings::currency());

        $this->setPaymentGateway(['currency' => 'GBP']); // sin default_currency
        $this->assertSame('GBP', SiteSettings::currency());
    }

    public function test_reads_global_booking_policy(): void
    {
        $this->assertSame(0, SiteSettings::minAdvanceDays());
        $this->assertSame(0, SiteSettings::cancellationHours());

        $this->setApp(['booking_min_advance_days' => 3, 'booking_cancellation_hours' => 48]);

        $this->assertSame(3, SiteSettings::minAdvanceDays());
        $this->assertSame(48, SiteSettings::cancellationHours());
    }
}
