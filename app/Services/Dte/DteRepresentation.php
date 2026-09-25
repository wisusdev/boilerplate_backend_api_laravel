<?php

namespace App\Services\Dte;

use App\Models\DteDocument;
use App\Support\Dte\SvCatalogs;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Lo que recibe el receptor (Manual Funcional v2, IV):
 *
 *  - el archivo DTE: el JSON firmado con `firmaElectronica` y, cuando lo tiene,
 *    `selloRecibido`. Es el documento con validez tributaria;
 *  - la representación gráfica (PDF), su versión legible.
 *
 * Ambos salen SIEMPRE del JSON guardado del DTE, nunca de la factura: la
 * factura podría decir otra cosa que lo transmitido. El diseño del PDF es
 * libre; sigue la representación sugerida por el manual.
 */
class DteRepresentation
{
    private const TITULOS = [
        '01' => 'FACTURA',
        '03' => 'COMPROBANTE DE CRÉDITO FISCAL',
        '05' => 'NOTA DE CRÉDITO',
        '06' => 'NOTA DE DÉBITO',
    ];

    /** CAT-005 */
    private const TIPOS_CONTINGENCIA = [
        1 => 'No disponibilidad de sistema del MH',
        2 => 'No disponibilidad de sistema del emisor',
        3 => 'Falla en el suministro de servicio de Internet del emisor',
        4 => 'Falla en el suministro de servicio de energía eléctrica del emisor',
        5 => 'Otro',
    ];

    /** CAT-016 */
    private const CONDICIONES = [1 => 'Contado', 2 => 'A crédito', 3 => 'Otro'];

    /**
     * El archivo DTE que se entrega y se archiva: el documento tal como se
     * firmó, más la firma y el sello (Manual Funcional v2, XXIII).
     *
     * @return array<string, mixed>
     */
    public function finalDocument(DteDocument $doc): array
    {
        $final = $doc->document();
        $final['firmaElectronica'] = $doc->firma_electronica;
        if ($doc->sello_recibido) {
            $final['selloRecibido'] = $doc->sello_recibido;
        }

        return $final;
    }

    public function json(DteDocument $doc): string
    {
        return json_encode($this->finalDocument($doc), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function pdf(DteDocument $doc): string
    {
        return Pdf::loadView('pdf.dte.documento', ['d' => $this->data($doc)])
            ->setPaper('letter')
            // El QR va embebido como data URI; la lista global de dompdf no lo admite.
            ->setOption('allowed_protocols', [...config('dompdf.options.allowed_protocols', []), 'data://' => ['rules' => []]])
            // Va adjunto por correo: solo los glifos usados, no la fuente entera.
            ->setOption('enable_font_subsetting', true)
            ->output();
    }

    /** Nombre de los adjuntos: el código de generación identifica el DTE. */
    public function filename(DteDocument $doc, string $ext): string
    {
        return $doc->codigo_generacion.'.'.$ext;
    }

    /** Consulta pública del MH a la que apunta el QR. */
    public static function consultaUrl(string $ambiente, string $codigo, string $fecEmi): string
    {
        return 'https://admin.factura.gob.sv/consultaPublica?'.http_build_query([
            'ambiente' => $ambiente, 'codGen' => $codigo, 'fechaEmi' => $fecEmi,
        ]);
    }

    /**
     * Datos para la plantilla, leídos del JSON del DTE.
     *
     * @return array<string, mixed>
     */
    public function data(DteDocument $doc): array
    {
        $j = $doc->document();
        $id = $j['identificacion'] ?? [];
        $emisor = $j['emisor'] ?? [];
        $receptor = $j['receptor'] ?? null;
        $resumen = $j['resumen'] ?? [];
        $cat = SvCatalogs::all();

        $sello = match (true) {
            (bool) $doc->sello_recibido => $doc->sello_recibido,
            $doc->estado === DteDocument::CONTINGENCY => 'Pendiente: emitido en contingencia, se transmitirá al restablecerse el servicio del MH',
            default => 'Sin sello de recepción',
        };

        $identificacion = [
            'Código de generación' => $doc->codigo_generacion,
            'Número de control' => $id['numeroControl'] ?? $doc->numero_control,
            'Sello de recepción' => $sello,
            'Modelo de facturación' => ($id['tipoModelo'] ?? 1) == 2 ? 'Modelo de facturación diferido' : 'Modelo de facturación previo',
            'Tipo de transmisión' => ($id['tipoOperacion'] ?? 1) == 2 ? 'Transmisión por contingencia' : 'Transmisión normal',
            'Fecha y hora de generación' => trim(($id['fecEmi'] ?? '').' '.($id['horEmi'] ?? '')),
        ];
        if ($tc = $id['tipoContingencia'] ?? null) {
            $identificacion['Tipo de contingencia'] = $tc.' — '.(self::TIPOS_CONTINGENCIA[$tc] ?? '');
            if ($m = $id['motivoContin'] ?? null) {
                $identificacion['Motivo de contingencia'] = $m;
            }
        }

        $receptorFilas = null;
        if (is_array($receptor)) {
            $docLabel = ($t = $receptor['tipoDocumento'] ?? null)
                ? self::nombre($cat['tipos_documento'], $t)
                : 'Documento de identificación';
            $receptorFilas = [
                'Nombre' => $receptor['nombre'] ?? null,
                ...(isset($receptor['nit']) ? ['NIT' => $receptor['nit']] : [$docLabel => $receptor['numDocumento'] ?? null]),
                'NRC' => $receptor['nrc'] ?? null,
                'Actividad económica' => $receptor['descActividad'] ?? null,
                'Dirección' => $this->direccion($receptor['direccion'] ?? null),
                'Teléfono' => $receptor['telefono'] ?? null,
                'Correo electrónico' => $receptor['correo'] ?? null,
            ];
        }

        $esFactura = ($id['tipoDte'] ?? $doc->tipo_dte) === '01';
        $totales = [
            ['Suma de ventas no sujetas', $resumen['totalNoSuj'] ?? 0, false],
            ['Suma de ventas exentas', $resumen['totalExenta'] ?? 0, false],
            ['Suma de ventas gravadas', $resumen['totalGravada'] ?? 0, false],
            ['Suma total de operaciones', $resumen['subTotalVentas'] ?? 0, true],
            ['Descuentos', $resumen['totalDescu'] ?? 0, false],
        ];
        foreach ((array) ($resumen['tributos'] ?? []) as $t) {
            $totales[] = [$t['descripcion'] ?? $t['codigo'], $t['valor'] ?? 0, false];
        }
        if (array_key_exists('subTotal', $resumen)) { // las notas no lo llevan
            $totales[] = ['Sub-total', $resumen['subTotal'], true];
        }
        if ($esFactura) {
            $totales[] = ['IVA incluido en ventas gravadas (13%)', $resumen['totalIva'] ?? 0, false];
        }
        if (($resumen['ivaPerci'] ?? 0) > 0) {
            $totales[] = ['IVA percibido', $resumen['ivaPerci'], false];
        }
        if (($resumen['ivaRete'] ?? 0) > 0) {
            $totales[] = ['IVA retenido', $resumen['ivaRete'], false];
        }
        $totales[] = ['Monto total de la operación', $resumen['montoTotalOperacion'] ?? 0, true];
        $totales[] = ['Otros montos no afectos', $resumen['totalNoGravado'] ?? 0, false];
        $totales[] = ['Total a pagar', $resumen['totalPagar'] ?? 0, true];

        return [
            'titulo' => self::TITULOS[$doc->tipo_dte] ?? 'DTE '.$doc->tipo_dte,
            'version' => $id['version'] ?? null,
            'pruebas' => ($id['ambiente'] ?? $doc->ambiente) === DteConfig::AMBIENTE_PRUEBAS,
            'invalidado' => $doc->estado === DteDocument::INVALIDATED,
            'identificacion' => $identificacion,
            // El QR lleva a la consulta pública: solo tiene sentido con sello.
            'qr' => $doc->sello_recibido
                ? $this->qr(self::consultaUrl($id['ambiente'] ?? $doc->ambiente, $doc->codigo_generacion, $id['fecEmi'] ?? ''))
                : null,
            'emisor' => [
                'Nombre' => $emisor['nombre'] ?? null,
                'NIT' => $emisor['nit'] ?? null,
                'NRC' => $emisor['nrc'] ?? null,
                'Actividad económica' => $emisor['descActividad'] ?? null,
                'Dirección' => $this->direccion($emisor['direccion'] ?? null),
                'Teléfono' => $emisor['telefono'] ?? null,
                'Correo electrónico' => $emisor['correo'] ?? null,
                'Nombre comercial' => $emisor['nombreComercial'] ?? null,
            ],
            'receptor' => $receptorFilas,
            // Secciones "D" de la Normativa: con su nombre y un guion si no se usan.
            'relacionados' => (array) ($j['documentoRelacionado'] ?? []),
            'ventaTercero' => $j['ventaTercero'] ?? null,
            'otrosDocumentos' => (array) ($j['otrosDocumentos'] ?? []),
            'items' => array_map(fn ($it) => [
                'num' => $it['numItem'] ?? '',
                'cantidad' => rtrim(rtrim(number_format((float) ($it['cantidad'] ?? 0), 4, '.', ''), '0'), '.'),
                'unidad' => self::nombre($cat['unidades_medida'], (string) ($it['uniMedida'] ?? '')),
                'descripcion' => $it['descripcion'] ?? '',
                'precio' => $it['precioUni'] ?? 0,
                'descuento' => $it['montoDescu'] ?? 0,
                'noGravado' => $it['noGravado'] ?? 0,
                'noSujeta' => $it['ventaNoSuj'] ?? 0,
                'exenta' => $it['ventaExenta'] ?? 0,
                'gravada' => $it['ventaGravada'] ?? 0,
            ], (array) ($j['cuerpoDocumento'] ?? [])),
            'totales' => $totales,
            'letras' => $resumen['totalLetras'] ?? null,
            'condicion' => self::CONDICIONES[$resumen['condicionOperacion'] ?? 1] ?? '-',
            'pagos' => array_map(fn ($p) => [
                // Con 99 ("otros"), la referencia es la que nombra el medio: "Pago en línea".
                'forma' => ($p['codigo'] ?? '') === '99' && ! empty($p['referencia'])
                    ? $p['referencia']
                    : self::nombre($cat['formas_pago'], (string) ($p['codigo'] ?? '')),
                'monto' => $p['montoPago'] ?? 0,
            ], (array) ($resumen['pagos'] ?? [])),
            'observaciones' => $resumen['observaciones'] ?? null,
            'apendice' => (array) ($j['apendice'] ?? []),
        ];
    }

    private function qr(string $url): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => 4,
            'eccLevel' => EccLevel::M,
        ])))->render($url);
    }

    /** "complemento, distrito, municipio, departamento", con los nombres del catálogo. */
    private function direccion(?array $d): ?string
    {
        if (! $d) {
            return null;
        }
        $cat = SvCatalogs::all();
        $dep = (string) ($d['departamento'] ?? '');

        return implode(', ', array_filter([
            $d['complemento'] ?? null,
            isset($d['distrito']) ? self::nombre($cat['distritos'][$dep] ?? [], $d['distrito']) : null,
            isset($d['municipio']) ? self::nombre($cat['municipios'][$dep] ?? [], $d['municipio']) : null,
            $dep !== '' ? self::nombre($cat['departamentos'], $dep) : null,
        ]));
    }

    /** @param  array<int, array{code: string, name: string}>  $list */
    private static function nombre(array $list, string $code): string
    {
        foreach ($list as $e) {
            if ($e['code'] === $code) {
                return $e['name'];
            }
        }

        return $code;
    }
}
