<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Acceso a la configuración GLOBAL del sitio que aplica por igual a todos los
 * tours y rentas de vehículos: moneda, antelación mínima de reserva y ventana
 * de cancelación. Antes vivían por entidad; ahora son ajustes del sitio.
 *
 * Los valores se cachean por request.
 */
class SiteSettings
{
    private static ?array $app = null;

    private static ?array $pg = null;

    /** Moneda global (código ISO, p. ej. "USD"). */
    public static function currency(): string
    {
        $pg = self::paymentGateway();

        return (string) ($pg['default_currency'] ?? $pg['currency'] ?? 'USD');
    }

    /** Símbolo de la moneda global (p. ej. "$"). */
    public static function currencySymbol(): string
    {
        $pg = self::paymentGateway();

        return (string) ($pg['currency_symbol'] ?? '$');
    }

    /** Antelación mínima de reserva, en días (0 = sin restricción). */
    public static function minAdvanceDays(): int
    {
        return (int) (self::app()['booking_min_advance_days'] ?? 0);
    }

    /** Ventana de cancelación previa al inicio, en horas (0 = sin restricción). */
    public static function cancellationHours(): int
    {
        return (int) (self::app()['booking_cancellation_hours'] ?? 0);
    }

    /** Limpia la caché (útil en tests o tras actualizar settings). */
    public static function flush(): void
    {
        self::$app = null;
        self::$pg = null;
    }

    private static function app(): array
    {
        return self::$app ??= self::read('app');
    }

    private static function paymentGateway(): array
    {
        return self::$pg ??= self::read('payment_gateway');
    }

    private static function read(string $key): array
    {
        $row = Setting::query()->where('key', $key)->first();

        return json_decode($row->value ?? '{}', true) ?: [];
    }
}
