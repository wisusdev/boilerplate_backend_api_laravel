<?php

namespace App\Services\Dte;

use App\Models\PurchaseDocument;
use App\Support\Dte\SvCatalogs;
use Carbon\CarbonInterface;

/**
 * Comprobante de retención (07, fe-cr-v2.json): lo emite vamosPues como agente
 * de retención designado al proveedor contribuyente al que retiene IVA sobre
 * los documentos que él emitió (uno por línea).
 *
 * CAT-006: 22 retención IVA 1 %, C4 retención IVA 13 %, C9 otras retenciones
 * (casos especiales, monto indicado a mano).
 */
class RetencionBuilder
{
    public const CODIGOS = ['22' => 0.01, 'C4' => 0.13, 'C9' => null];

    /**
     * IVA retenido de una línea, en centavos.
     *
     * @param  array<string, mixed>  $item
     */
    public static function ivaRetenido(array $item): int
    {
        $tasa = self::CODIGOS[$item['codigo_retencion']] ?? null;

        return $tasa === null
            ? DteParts::cents($item['iva_retenido'] ?? 0)
            : (int) round(DteParts::cents($item['monto_sujeto']) * $tasa);
    }

    /** @return array<string, mixed> */
    public function build(PurchaseDocument $doc, DteConfig $config, string $numeroControl, string $codigo, CarbonInterface $now): array
    {
        $cuerpo = [];
        $sujetoCents = $retenidoCents = 0;
        foreach ($doc->items as $item) {
            $montoCents = DteParts::cents($item['monto_sujeto']);
            $retenido = self::ivaRetenido($item);
            $electronico = (int) $item['tipo_generacion'] === 2;

            $cuerpo[] = [
                'numItem' => count($cuerpo) + 1,
                'tipoDte' => $item['tipo_dte'],
                'tipoGeneracion' => (int) $item['tipo_generacion'],   // CAT-007
                'numeroDocumento' => $electronico ? strtoupper(trim($item['numero_documento'])) : trim($item['numero_documento']),
                'fechaEmision' => $item['fecha_emision'],
                'montoSujetoGrav' => $montoCents / 100,
                'codigoRetencionMH' => $item['codigo_retencion'],
                'ivaRetenido' => $retenido / 100,
                'descripcion' => DteParts::optional($item['descripcion'] ?? null, 1, 1000),
            ];
            $sujetoCents += $montoCents;
            $retenidoCents += $retenido;
        }

        [$tipoDoc, $numDoc] = FacturaBuilder::documento($doc->proveedor_tipo_documento, $doc->proveedor_num_documento);

        return [
            'identificacion' => DteParts::identificacion('07', $config, $numeroControl, $codigo, $now),
            'emisor' => DteParts::only(DteParts::emisor($config), [
                'nit', 'nrc', 'nombre', 'codActividad', 'descActividad', 'nombreComercial', 'direccion', 'codEstable', 'codPuntoVenta', 'telefono', 'correo',
            ]),
            'receptor' => [
                'tipoDocumento' => $tipoDoc,
                'numDocumento' => $numDoc,
                'nrc' => DteParts::optional(DteConfig::digits($doc->proveedor_nrc), 2, 8),
                'nombre' => mb_substr($doc->proveedor_nombre, 0, 250),
                'codActividad' => (string) $doc->proveedor_cod_actividad,
                'descActividad' => mb_substr((string) SvCatalogs::actividad($doc->proveedor_cod_actividad), 0, 150),
                'nombreComercial' => DteParts::optional($doc->proveedor_nombre_comercial, 1, 150),
                'direccion' => DteParts::direccion(
                    $doc->proveedor_departamento, $doc->proveedor_municipio, $doc->proveedor_distrito, $doc->proveedor_direccion,
                ),
                'telefono' => DteParts::optional($doc->proveedor_telefono, 8, 30),
                'correo' => DteParts::optional($doc->proveedor_correo, 6, 100),
            ],
            'cuerpoDocumento' => $cuerpo,
            'resumen' => [
                'totalSujetoRetencion' => $sujetoCents / 100,
                'totalIva' => ((int) round($sujetoCents * DteParts::IVA_RATE)) / 100,
                'totalIvaRetenido' => $retenidoCents / 100,
                'totalLetras' => AmountToWords::convert($retenidoCents),
                'observaciones' => DteParts::optional($doc->observaciones, 1, 3000),
            ],
            'apendice' => [[
                'campo' => 'compra',
                'etiqueta' => 'Documento interno',
                'valor' => $doc->number,
            ]],
        ];
    }
}
