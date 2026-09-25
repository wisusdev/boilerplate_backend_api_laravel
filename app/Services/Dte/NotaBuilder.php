<?php

namespace App\Services\Dte;

use App\Models\CreditNote;
use App\Models\DteDocument;
use Carbon\CarbonInterface;

/**
 * Nota de crédito (05, fe-nc-v4.json) y nota de débito (06, fe-nd-v4.json).
 *
 * Ajustan un CCF sellado, así que se construyen desde su JSON:
 *  - documentoRelacionado apunta al CCF (tipo 03, generación electrónica, su
 *    código de generación y fecha);
 *  - el receptor es el del CCF, identificado por NIT (tipo 36);
 *  - importes sin IVA; el IVA va por línea (totalIva) y como tributo 20;
 *  - retención y percepción repiten lo que declaró el CCF, proporcionales al
 *    ajuste (codigoRetencionMH 22 = retención IVA 1 %, CAT-006).
 *
 * La nota de débito solo añade numPagoElectronico al resumen.
 */
class NotaBuilder
{
    /** @return array<string, mixed> */
    public function build(CreditNote $note, DteDocument $ccf, DteConfig $config, string $numeroControl, string $codigo, CarbonInterface $now): array
    {
        $note->loadMissing('items');
        $tipo = $note->tipoDte();
        $ccfJson = $ccf->document();
        $ccfResumen = $ccfJson['resumen'] ?? [];
        $receptorCcf = $ccfJson['receptor'] ?? [];

        $cuerpo = [];
        $gravadaCents = $ivaCents = $reteCents = $perciCents = 0;
        foreach ($note->items as $item) {
            $baseCents = DteParts::cents($item->total);
            $ivaLinea = (int) round($baseCents * DteParts::IVA_RATE);
            [$rete, $perci] = IvaAjuste::comoEnCcf($config, $ccfResumen, $baseCents);

            $cuerpo[] = [
                'numItem' => count($cuerpo) + 1,
                'tipoItem' => 2,                       // CAT-011: servicio
                'numeroDocumento' => $ccf->codigo_generacion,
                'cantidad' => (int) $item->quantity,
                'codigo' => null,
                'codTributo' => null,
                'uniMedida' => 99,
                'descripcion' => mb_substr($item->description, 0, 1000),
                'precioUni' => round($baseCents / 100 / max((int) $item->quantity, 1), 8),
                'montoDescu' => 0,
                'ventaNoSuj' => 0,
                'ventaExenta' => 0,
                'ventaGravada' => $baseCents / 100,
                'tributos' => ['20'],
                'noGravado' => 0,
                'ivaPerci' => $perci / 100,
                'totalIva' => $ivaLinea / 100,
                'ivaRete' => $rete / 100,
            ];

            $gravadaCents += $baseCents;
            $ivaCents += $ivaLinea;
            $reteCents += $rete;
            $perciCents += $perci;
        }

        $montoCents = $gravadaCents + $ivaCents;
        $pagarCents = $montoCents - $reteCents + $perciCents;

        $resumen = [
            'totalNoSuj' => 0,
            'totalExenta' => 0,
            'totalGravada' => $gravadaCents / 100,
            'subTotalVentas' => $gravadaCents / 100,
            'totalDescu' => 0,
            'tributos' => [['codigo' => '20', 'descripcion' => 'Impuesto al Valor Agregado 13%', 'valor' => $ivaCents / 100]],
            'montoTotalOperacion' => $montoCents / 100,
            'ivaPerci' => $perciCents / 100,
            'totalIva' => $ivaCents / 100,
            'ivaRete' => $reteCents / 100,
            'totalNoGravado' => 0,
            'totalPagar' => $pagarCents / 100,
            'totalLetras' => AmountToWords::convert($pagarCents),
            'condicionOperacion' => 1,
            'observaciones' => DteParts::optional($note->motivo, 1, 3000),
            // Por validar en apitest: el esquema solo dice "código retención MH".
            'codigoRetencionMH' => $reteCents > 0 ? '22' : null,
        ];
        if ($tipo === '06') {
            $resumen['numPagoElectronico'] = null;
        }

        return [
            'identificacion' => DteParts::identificacion($tipo, $config, $numeroControl, $codigo, $now, fusion: true),
            'documentoRelacionado' => [[
                'tipoDocumento' => FacturaBuilder::TIPO_CCF,
                'tipoGeneracion' => 2,                 // CAT-007: electrónico
                'numeroDocumento' => $ccf->codigo_generacion,
                'fechaEmision' => $ccfJson['identificacion']['fecEmi'] ?? $ccf->created_at->format('Y-m-d'),
            ]],
            'emisor' => DteParts::only(DteParts::emisor($config), [
                'nit', 'nrc', 'nombre', 'codActividad', 'descActividad', 'nombreComercial', 'direccion', 'telefono', 'correo',
            ]),
            'receptor' => [
                'tipoDocumento' => '36',
                'numDocumento' => $receptorCcf['nit'] ?? '',
                'nrc' => $receptorCcf['nrc'] ?? null,
                'nombre' => $receptorCcf['nombre'] ?? '',
                'codActividad' => $receptorCcf['codActividad'] ?? '',
                'descActividad' => $receptorCcf['descActividad'] ?? '',
                'nombreComercial' => $receptorCcf['nombreComercial'] ?? null,
                'direccion' => $receptorCcf['direccion'] ?? null,
                'telefono' => $receptorCcf['telefono'] ?? null,
                'correo' => $receptorCcf['correo'] ?? null,
            ],
            'ventaTercero' => null,
            'cuerpoDocumento' => $cuerpo,
            'resumen' => $resumen,
            'apendice' => [[
                'campo' => 'nota',
                'etiqueta' => $note->isDebit() ? 'Nota de débito interna' : 'Nota de crédito interna',
                'valor' => $note->number,
            ]],
        ];
    }
}
