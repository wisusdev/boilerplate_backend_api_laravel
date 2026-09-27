<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Textos legales del sitio: términos y condiciones, política de privacidad y
 * política de cancelación. Se editan desde el panel (Markdown) y se guardan en
 * la fila `legal` de settings; mientras no se editen, se sirve el borrador de
 * resources/legal.
 *
 * Los textos admiten marcadores ({{correo}}, {{horas_cancelacion}}...) que se
 * rellenan al mostrarlos con los ajustes vigentes: cambiar el correo de
 * contacto no obliga a reescribir las políticas.
 */
class LegalDocuments
{
    public const DOCUMENTS = [
        'terminos' => 'Términos y condiciones',
        'privacidad' => 'Política de privacidad',
        'cancelacion' => 'Política de cancelación y reembolsos',
    ];

    /** Versión que se registra al aceptar mientras nadie ha editado los textos. */
    public const DRAFT_VERSION = 'borrador';

    public const MAX_LENGTH = 60000;

    public static function exists(string $slug): bool
    {
        return array_key_exists($slug, self::DOCUMENTS);
    }

    /**
     * @return array{slug:string,title:string,content:string,html:string,updated_at:?string,is_draft:bool}
     */
    public static function get(string $slug): array
    {
        $guardado = self::stored()[$slug] ?? null;
        $content = is_array($guardado) && is_string($guardado['content'] ?? null)
            ? $guardado['content']
            : self::draft($slug);

        return [
            'slug' => $slug,
            'title' => self::DOCUMENTS[$slug],
            'content' => $content,
            'html' => self::html($content),
            'updated_at' => $guardado['updated_at'] ?? null,
            'is_draft' => $guardado === null,
        ];
    }

    /** @return list<array{slug:string,title:string,updated_at:?string,is_draft:bool}> */
    public static function index(): array
    {
        $guardados = self::stored();

        return array_map(fn (string $slug) => [
            'slug' => $slug,
            'title' => self::DOCUMENTS[$slug],
            'updated_at' => $guardados[$slug]['updated_at'] ?? null,
            'is_draft' => ! isset($guardados[$slug]),
        ], array_keys(self::DOCUMENTS));
    }

    public static function save(string $slug, string $content): array
    {
        $guardados = self::stored();
        $guardados[$slug] = [
            'content' => str_replace("\r\n", "\n", $content),
            'updated_at' => now()->toIso8601String(),
        ];
        Setting::updateOrCreate(['key' => 'legal'], ['value' => json_encode($guardados)]);

        return self::get($slug);
    }

    /**
     * Versión vigente del conjunto, la que se guarda al aceptar: la fecha de la
     * última edición de cualquiera de los textos. Así queda constancia de qué
     * redacción aceptó cada cliente.
     */
    public static function version(): string
    {
        $fechas = array_filter(array_column(self::stored(), 'updated_at'));

        return $fechas ? max($fechas) : self::DRAFT_VERSION;
    }

    /**
     * Markdown a HTML sin HTML crudo ni enlaces javascript:, así el texto del
     * panel no puede inyectar scripts en la página pública.
     */
    public static function html(string $markdown): string
    {
        $html = Str::markdown(self::fill($markdown), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Resalta lo que falta por completar para que no pase desapercibido.
        return preg_replace('/\[pendiente:[^\]<]*\]/u', '<mark class="legal-pending">$0</mark>', $html) ?? $html;
    }

    /** Sustituye los marcadores por los datos del sitio. */
    public static function fill(string $markdown): string
    {
        $app = self::setting('app');
        $dte = self::setting('dte');

        $pendiente = fn (string $que) => "[pendiente: {$que}]";
        $valor = fn (mixed $v, string $que) => is_string($v) && trim($v) !== '' ? trim($v) : $pendiente($que);

        $direccion = implode(', ', array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : '',
            [$app['contact_address'] ?? '', $app['contact_city'] ?? '', $app['contact_country'] ?? ''],
        )));
        $horas = SiteSettings::cancellationHours();

        $datos = [
            'sitio' => SiteSettings::name(),
            'razon_social' => $valor($dte['dte_nombre'] ?? null, 'razón social'),
            'nit' => $valor($dte['dte_nit'] ?? null, 'NIT'),
            'direccion' => $direccion !== '' ? $direccion : $pendiente('dirección'),
            'correo' => $valor($app['contact_email'] ?? null, 'correo de contacto'),
            'telefono' => $valor($app['contact_phone'] ?? null, 'teléfono'),
            'web' => rtrim((string) config('app.frontend_url'), '/'),
            'horas_cancelacion' => $horas > 0 ? "{$horas} horas" : $pendiente('horas de anticipación'),
        ];

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m) => $datos[$m[1]] ?? $m[0],
            $markdown,
        ) ?? $markdown;
    }

    private static function draft(string $slug): string
    {
        $ruta = resource_path("legal/{$slug}.md");

        return is_file($ruta) ? (string) file_get_contents($ruta) : '';
    }

    /** @return array<string,array{content:string,updated_at:string}> */
    private static function stored(): array
    {
        $guardados = self::setting('legal');

        return array_intersect_key($guardados, self::DOCUMENTS);
    }

    private static function setting(string $key): array
    {
        $row = Setting::query()->where('key', $key)->first();

        return json_decode($row->value ?? '{}', true) ?: [];
    }
}
