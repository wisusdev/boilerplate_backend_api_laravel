<?php

namespace App\Services\Dte;

use App\Models\PurchaseDocument;
use App\Support\Dte\SvCatalogs;
use Carbon\CarbonInterface;

/**
 * Factura de sujeto excluido (14, fe-fse-v2.json): la emite vamosPues al
 * comprar a quien no es contribuyente del IVA. El "receptor" del DTE es el
 * proveedor; el emisor, vamosPues.
 *
 * No hay IVA. compra = cantidad × precio − descuento. Si se retiene renta (10 %
 * a una persona natural que presta un servicio), totalPagar la descuenta.
 */
class SujetoExcluidoBuilder
{
    public const RETENCION_RENTA = 0.10;

    /** @return array<string, mixed> */
    public function build(PurchaseDocument $doc, DteConfig $config, string $numeroControl, string $codigo, CarbonInterface $now): array
    {
        $cuerpo = [];
        $compraCents = $descuentoCents = 0;
        foreach ($doc->items as $item) {
            $cantidad = max((int) $item['quantity'], 1);
            $brutoCents = DteParts::cents($item['unit_price']) * $cantidad;
            $descCents = DteParts::cents($item['descuento'] ?? 0);
            $servicio = (int) ($item['tipo_item'] ?? 2) === 2;

            $cuerpo[] = [
                'numItem' => count($cuerpo) + 1,
                'tipoItem' => $servicio ? 2 : 1,        // CAT-011
                'cantidad' => $cantidad,
                'codigo' => null,
                'uniMedida' => $servicio ? 99 : 59,     // CAT-014: otra / unidad
                'descripcion' => mb_substr($item['description'], 0, 1000),
                'precioUni' => round((float) $item['unit_price'], 8),
                'montoDescu' => $descCents / 100,
                'compra' => ($brutoCents - $descCents) / 100,
            ];
            $compraCents += $brutoCents - $descCents;
            $descuentoCents += $descCents;
        }

        $renta = $doc->retener_renta ? (int) round($compraCents * self::RETENCION_RENTA) : 0;
        $pagar = $compraCents - $renta;
        $credito = $doc->condicion_operacion === 2;

        [$tipoDoc, $numDoc] = FacturaBuilder::documento($doc->proveedor_tipo_documento, $doc->proveedor_num_documento);

        return [
            'identificacion' => DteParts::identificacion('14', $config, $numeroControl, $codigo, $now),
            'emisor' => DteParts::only(DteParts::emisor($config), [
                'nit', 'nrc', 'nombre', 'codActividad', 'descActividad', 'direccion', 'telefono', 'codEstable', 'codPuntoVenta', 'correo',
            ]),
            'receptor' => [
                'tipoDocumento' => $tipoDoc,
                'numDocumento' => $numDoc,
                'nombre' => mb_substr($doc->proveedor_nombre, 0, 250),
                'codActividad' => SvCatalogs::actividad($doc->proveedor_cod_actividad) ? $doc->proveedor_cod_actividad : null,
                'descActividad' => DteParts::optional(SvCatalogs::actividad($doc->proveedor_cod_actividad), 5, 150),
                'direccion' => DteParts::direccion(
                    $doc->proveedor_departamento, $doc->proveedor_municipio, $doc->proveedor_distrito, $doc->proveedor_direccion,
                ),
                'telefono' => DteParts::optional($doc->proveedor_telefono, 8, 30),
                'correo' => DteParts::optional($doc->proveedor_correo, 6, 100),
            ],
            'cuerpoDocumento' => $cuerpo,
            'resumen' => [
                'totalCompra' => $compraCents / 100,
                'descu' => 0,
                'totalDescu' => $descuentoCents / 100,
                'subTotal' => $compraCents / 100,
                'reteRenta' => $renta / 100,
                'totalPagar' => $pagar / 100,
                'totalLetras' => AmountToWords::convert($pagar),
                'condicionOperacion' => $credito ? 2 : 1,
                // A crédito no hay pago todavía.
                'pagos' => $credito ? null : [[
                    'codigo' => $doc->forma_pago ?: '01',
                    'montoPago' => $pagar / 100,
                    'referencia' => null,
                    'plazo' => null,
                    'periodo' => null,
                ]],
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
