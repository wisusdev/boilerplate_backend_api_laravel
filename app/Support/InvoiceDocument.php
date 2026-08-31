<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Documento imprimible de una factura.
 *
 * Prepara UNA sola estructura de datos que consumen las tres plantillas, de modo
 * que los diseños solo se diferencian en presentación y no en cálculos: si el
 * desglose cambia, cambia en un sitio.
 */
class InvoiceDocument
{
    /** Diseños disponibles; la clave se guarda en los ajustes del sitio. */
    public const TEMPLATES = [
        'clasica' => 'Clásica',
        'moderna' => 'Moderna',
        'minimal' => 'Minimalista',
    ];

    public const DEFAULT_TEMPLATE = 'clasica';

    /** Plantilla configurada, con respaldo si el ajuste trae algo desconocido. */
    public static function template(): string
    {
        $elegida = (string) (self::appSettings()['invoice_template'] ?? '');

        return array_key_exists($elegida, self::TEMPLATES) ? $elegida : self::DEFAULT_TEMPLATE;
    }

    public static function pdf(Invoice $invoice, ?string $template = null): string
    {
        $plantilla = $template && array_key_exists($template, self::TEMPLATES)
            ? $template
            : self::template();

        return Pdf::loadView("pdf.invoice.{$plantilla}", ['d' => self::data($invoice)])->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return 'factura-'.$invoice->number.'.pdf';
    }

    /**
     * Datos del documento. Las plantillas solo leen de aquí.
     *
     * @return array<string,mixed>
     */
    public static function data(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'booking.user', 'booking.bookable']);
        $ajustes = self::appSettings();
        $fiscal = self::settings('dte');

        $conceptos = $invoice->items->map(fn ($i) => [
            'descripcion' => $i->description,
            'cantidad' => (int) $i->quantity,
            'unitario' => (float) $i->unit_price,
            'importe' => (float) $i->total,
        ])->all();

        // Una factura nacida de una reserva no tiene líneas propias: se muestra la
        // reserva como concepto único para que el documento nunca salga vacío.
        if ($conceptos === [] && $invoice->booking) {
            $conceptos = [[
                'descripcion' => $invoice->booking->bookable?->title ?? ('Reserva #'.$invoice->booking->id),
                'cantidad' => (int) ($invoice->booking->party_size ?: 1),
                'unitario' => round((float) $invoice->amount / max((int) $invoice->booking->party_size, 1), 2),
                'importe' => (float) $invoice->amount,
            ]];
        }

        $cliente = [
            'nombre' => $invoice->receptor_name ?: trim((string) $invoice->booking?->user?->name) ?: 'Consumidor final',
            'documento' => $invoice->receptor_document,
            'correo' => $invoice->receptor_email ?: $invoice->booking?->user?->email,
        ];

        return [
            'numero' => $invoice->number,
            'emitido' => $invoice->issued_at ?? $invoice->created_at,
            'estado' => $invoice->status,
            'estadoEtiqueta' => ['paid' => 'Pagada', 'pending' => 'Pendiente', 'cancelled' => 'Anulada'][$invoice->status] ?? $invoice->status,
            'moneda' => $invoice->currency_code ?: SiteSettings::currency(),
            'conceptos' => $conceptos,
            'subtotal' => round(array_sum(array_column($conceptos, 'importe')), 2),
            'total' => (float) $invoice->amount,
            'notas' => $invoice->notes,
            'reserva' => $invoice->booking?->id,
            'dteNumero' => $invoice->dte_number,
            'cliente' => $cliente,
            'emisor' => [
                'nombre' => trim($fiscal['dte_nombre'] ?? '') ?: SiteSettings::name(),
                'fiscal' => array_values(array_filter([
                    ! empty($fiscal['dte_nit']) ? 'NIT: '.$fiscal['dte_nit'] : null,
                    ! empty($fiscal['dte_nrc']) ? 'NRC: '.$fiscal['dte_nrc'] : null,
                ])),
                'lineas' => array_values(array_filter([
                    trim($fiscal['dte_direccion'] ?? '') ?: trim($ajustes['contact_address'] ?? ''),
                    trim(implode(', ', array_filter([$ajustes['contact_city'] ?? null, $ajustes['contact_country'] ?? null]))),
                    trim($fiscal['dte_telefono'] ?? '') ?: trim($ajustes['contact_phone'] ?? ''),
                    trim($fiscal['dte_correo'] ?? '') ?: trim($ajustes['contact_email'] ?? ''),
                ])),
                'logo' => self::logoPath($ajustes),
            ],
        ];
    }

    /**
     * El logo solo se embebe si es un fichero local: descargarlo durante la
     * generación del PDF la haría lenta y frágil.
     *
     * @param  array<string,mixed>  $ajustes
     */
    private static function logoPath(array $ajustes): ?string
    {
        $url = $ajustes['app_logo_dark_url'] ?? $ajustes['app_logo_url'] ?? null;

        if (! $url || ($pos = strpos($url, '/storage/')) === false) {
            return null;
        }

        $ruta = storage_path('app/public/'.ltrim(substr($url, $pos + 9), '/'));

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function appSettings(): array
    {
        return self::settings('app');
    }

    /**
     * @return array<string,mixed>
     */
    private static function settings(string $key): array
    {
        $valor = Setting::where('key', $key)->value('value');

        return $valor ? (json_decode($valor, true) ?: []) : [];
    }
}
