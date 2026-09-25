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
        '07' => 'COMPROBANTE DE RETENCIÓN',
        '14' => 'FACTURA DE SUJETO EXCLUIDO',
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

        $tipo = $id['tipoDte'] ?? $doc->tipo_dte;
        [$tabla, $totales] = match ($tipo) {
            '14' => $this->sujetoExcluido($j, $cat),
            '07' => $this->retencion($j, $cat),
            default => $this->venta($j, $cat, $tipo),
        };

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
            'receptorTitulo' => match ($tipo) {
                '14' => 'SUJETO EXCLUIDO',
                '07' => 'SUJETO DE RETENCIÓN',
                default => 'RECEPTOR',
            },
            // Secciones "D" de la Normativa (solo documentos de venta y notas):
            // con su nombre y un guion si no se usan.
            'seccionesD' => in_array($tipo, ['01', '03', '05', '06'], true),
            'relacionados' => (array) ($j['documentoRelacionado'] ?? []),
            'ventaTercero' => $j['ventaTercero'] ?? null,
            'otrosDocumentos' => (array) ($j['otrosDocumentos'] ?? []),
            'tabla' => $tabla,
            'totales' => $totales,
            'letras' => $resumen['totalLetras'] ?? null,
            'condicion' => array_key_exists('condicionOperacion', $resumen)
                ? (self::CONDICIONES[$resumen['condicionOperacion']] ?? '-')
                : null,
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

    /**
     * Factura, CCF y notas: columnas de venta y resumen con tributos.
     *
     * @return array{0: array<string, mixed>, 1: list<array{0: string, 1: mixed, 2: bool}>}
     */
    private function venta(array $j, array $cat, string $tipo): array
    {
        $resumen = $j['resumen'] ?? [];
        $rows = array_map(fn ($it) => [
            $it['numItem'] ?? '',
            self::cantidad($it['cantidad'] ?? 0),
            self::nombre($cat['unidades_medida'], (string) ($it['uniMedida'] ?? '')),
            $it['descripcion'] ?? '',
            self::dinero($it['precioUni'] ?? 0),
            self::dinero($it['montoDescu'] ?? 0),
            self::dinero($it['noGravado'] ?? 0),
            self::dinero($it['ventaNoSuj'] ?? 0),
            self::dinero($it['ventaExenta'] ?? 0),
            self::dinero($it['ventaGravada'] ?? 0),
        ], (array) ($j['cuerpoDocumento'] ?? []));

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
        if ($tipo === '01') {
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

        return [[
            'cols' => [['N°', 'c'], ['Cant.', 'r'], ['Unidad', ''], ['Descripción', '', '30%'], ['Precio unit.', 'r'],
                ['Descuento', 'r'], ['Otros montos no afectos', 'r'], ['Ventas no sujetas', 'r'], ['Ventas exentas', 'r'], ['Ventas gravadas', 'r']],
            'rows' => $rows,
        ], $totales];
    }

    /** Sujeto excluido: compras, sin IVA, con retención de renta. */
    private function sujetoExcluido(array $j, array $cat): array
    {
        $r = $j['resumen'] ?? [];

        return [[
            'cols' => [['N°', 'c'], ['Cant.', 'r'], ['Unidad', ''], ['Descripción', '', '40%'], ['Precio unit.', 'r'], ['Descuento', 'r'], ['Compra', 'r']],
            'rows' => array_map(fn ($it) => [
                $it['numItem'] ?? '',
                self::cantidad($it['cantidad'] ?? 0),
                self::nombre($cat['unidades_medida'], (string) ($it['uniMedida'] ?? '')),
                $it['descripcion'] ?? '',
                self::dinero($it['precioUni'] ?? 0),
                self::dinero($it['montoDescu'] ?? 0),
                self::dinero($it['compra'] ?? 0),
            ], (array) ($j['cuerpoDocumento'] ?? [])),
        ], [
            ['Total de operaciones', $r['totalCompra'] ?? 0, true],
            ['Descuento global', $r['descu'] ?? 0, false],
            ['Total descuentos', $r['totalDescu'] ?? 0, false],
            ['Sub-total', $r['subTotal'] ?? 0, true],
            ['Retención de renta', $r['reteRenta'] ?? 0, false],
            ['Total a pagar', $r['totalPagar'] ?? 0, true],
        ]];
    }

    /** Comprobante de retención: un documento retenido por línea. */
    private function retencion(array $j, array $cat): array
    {
        $r = $j['resumen'] ?? [];
        $tipos = array_column(self::TIPOS_DOCUMENTO_RELACIONADO, 1, 0);

        return [[
            'cols' => [['N°', 'c'], ['Documento', ''], ['Generación', ''], ['N° de documento', '', '28%'], ['Fecha', ''],
                ['Monto sujeto', 'r'], ['Código', 'c'], ['IVA retenido', 'r'], ['Descripción', '']],
            'rows' => array_map(fn ($it) => [
                $it['numItem'] ?? '',
                $tipos[$it['tipoDte'] ?? ''] ?? ($it['tipoDte'] ?? ''),
                ((int) ($it['tipoGeneracion'] ?? 2)) === 1 ? 'Físico' : 'Electrónico',
                $it['numeroDocumento'] ?? '',
                $it['fechaEmision'] ?? '',
                self::dinero($it['montoSujetoGrav'] ?? 0),
                $it['codigoRetencionMH'] ?? '',
                self::dinero($it['ivaRetenido'] ?? 0),
                $it['descripcion'] ?? '-',
            ], (array) ($j['cuerpoDocumento'] ?? [])),
        ], [
            ['Total monto sujeto a retención', $r['totalSujetoRetencion'] ?? 0, true],
            ['IVA 13%', $r['totalIva'] ?? 0, false],
            ['Total IVA retenido', $r['totalIvaRetenido'] ?? 0, true],
        ]];
    }

    /** CAT-002, los que puede retener un comprobante de retención. */
    private const TIPOS_DOCUMENTO_RELACIONADO = [['01', 'Factura'], ['03', 'CCF'], ['14', 'Sujeto excluido']];

    private static function dinero(mixed $v): string
    {
        return '$'.number_format((float) $v, 2, '.', ',');
    }

    private static function cantidad(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
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
