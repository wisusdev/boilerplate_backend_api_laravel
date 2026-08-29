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

    /**
     * Nombre público del sitio. Sale de los ajustes para que renombrar el
     * proyecto sea un cambio de configuración y no de código.
     */
    public static function name(): string
    {
        $nombre = trim((string) (self::app()['app_name'] ?? ''));

        return $nombre !== '' ? $nombre : (string) config('app.name', 'Cusgo Adventures');
    }

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

    /** ¿Está activo el pago asistido por WhatsApp? */
    public static function whatsappPaymentEnabled(): bool
    {
        return filter_var(self::paymentGateway()['payment_whatsapp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Número de WhatsApp para el pago asistido, solo dígitos. Usa el específico
     * de pagos y, si no está configurado, el de contacto del sitio.
     */
    public static function whatsappNumber(): string
    {
        $number = (string) (self::paymentGateway()['payment_whatsapp_number'] ?? '');

        if (trim($number) === '') {
            $number = (string) (self::app()['contact_whatsapp'] ?? '');
        }

        return preg_replace('/\D/', '', $number) ?? '';
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
