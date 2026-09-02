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

    // ── Enlaces de pago del banco (BAC) ───────────────────────────────────────

    /** ¿Se ofrece el cobro con enlace de pago del banco? */
    public static function bacLinkEnabled(): bool
    {
        return filter_var(self::paymentGateway()['payment_bac_link_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Dominios a los que se permite apuntar un enlace pegado en el panel.
     *
     * No es paranoia: ese enlace se le envía al cliente por correo con nuestra
     * marca detrás, así que un error de copiado —o una cuenta de panel
     * comprometida— nos convierte en el aval de una web de phishing.
     *
     * Los valores por defecto son los dominios conocidos de BAC Credomatic. Si
     * el banco emite desde otro, se añade en Ajustes; el error de validación
     * dice cuál fue el host rechazado para que no haya que adivinarlo.
     *
     * @return array<int, string>
     */
    public static function bacLinkHosts(): array
    {
        $raw = self::paymentGateway()['payment_bac_link_hosts'] ?? null;

        $hosts = is_array($raw)
            ? $raw
            : preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        $hosts = array_values(array_filter(array_map(
            static fn ($h) => strtolower(trim((string) $h)),
            $hosts ?: []
        )));

        return $hosts !== [] ? $hosts : ['baccredomatic.com', 'credomatic.com'];
    }

    /** Horas de validez por defecto de un enlace recién emitido (0 = sin caducidad). */
    public static function bacLinkTtlHours(): int
    {
        $ttl = self::paymentGateway()['payment_bac_link_ttl_hours'] ?? 24;

        return max((int) $ttl, 0);
    }

    /**
     * ¿Quien emite el enlace tiene prohibido confirmarlo?
     *
     * Desactivado por defecto: un equipo de dos personas no siempre puede
     * permitirse el doble control, y bloquearlo de fábrica dejaría cobros sin
     * confirmar, que es peor que el riesgo que evita.
     */
    public static function bacDualControl(): bool
    {
        return filter_var(self::paymentGateway()['payment_bac_dual_control'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** Texto que acompaña al enlace en la pantalla del cliente. */
    public static function bacInstructions(): string
    {
        return trim((string) (self::paymentGateway()['payment_bac_instructions'] ?? ''));
    }

    /**
     * ¿Cancelar automáticamente la reserva cuando su enlace caduca sin uso?
     *
     * Apagado por defecto a propósito: cancelar una reserva en firme sin que
     * nadie lo revise es una acción de cara al cliente y difícil de deshacer si
     * el asiento ya se volvió a vender. Un sitio nuevo empieza solo con el aviso
     * (el enlace caduca, se notifica, un humano decide); activar esto es una
     * decisión explícita del operador, no un efecto colateral de instalar la fase.
     */
    public static function bacLinkAutoRelease(): bool
    {
        return filter_var(self::paymentGateway()['payment_bac_link_auto_release'] ?? false, FILTER_VALIDATE_BOOLEAN);
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
