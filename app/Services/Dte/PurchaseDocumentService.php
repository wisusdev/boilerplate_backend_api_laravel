<?php

namespace App\Services\Dte;

use App\Models\PurchaseDocument;
use App\Services\DteService;
use App\Support\Dte\SvCatalogs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Factura de sujeto excluido y comprobante de retención: se validan por
 * completo al guardarlos (todas las causas a la vez, con el proveedor delante)
 * y quien los crea emite el DTE a continuación.
 */
class PurchaseDocumentService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DteException
     */
    public function create(string $kind, array $data, ?string $userId = null): PurchaseDocument
    {
        $kind = $kind === PurchaseDocument::CR ? PurchaseDocument::CR : PurchaseDocument::FSE;
        if ($errors = $this->errors($kind, $data)) {
            throw DteException::invalid($errors);
        }

        $items = array_map(fn (array $i) => $kind === PurchaseDocument::CR
            ? [
                'tipo_dte' => $i['tipo_dte'],
                'tipo_generacion' => (int) $i['tipo_generacion'],
                'numero_documento' => trim($i['numero_documento']),
                'fecha_emision' => $i['fecha_emision'],
                'monto_sujeto' => round((float) $i['monto_sujeto'], 2),
                'codigo_retencion' => $i['codigo_retencion'],
                'iva_retenido' => RetencionBuilder::ivaRetenido($i) / 100,
                'descripcion' => trim((string) ($i['descripcion'] ?? '')) ?: null,
            ]
            : [
                'description' => trim($i['description']),
                'quantity' => max((int) $i['quantity'], 1),
                'unit_price' => round((float) $i['unit_price'], 2),
                'descuento' => round((float) ($i['descuento'] ?? 0), 2),
                'tipo_item' => (int) ($i['tipo_item'] ?? 2) === 1 ? 1 : 2,
            ], array_values($data['items']));

        return DB::transaction(function () use ($kind, $data, $items, $userId) {
            $prefijo = $kind === PurchaseDocument::CR ? 'CR' : 'FSE';
            $numero = DteService::nextNumber('IN', $kind === PurchaseDocument::CR ? 'CR' : 'SE');

            return PurchaseDocument::create([
                'kind' => $kind,
                'number' => sprintf('%s-%05d', $prefijo, $numero),
                'expense_id' => $data['expense_id'] ?? null,
                'proveedor_nombre' => trim($data['proveedor_nombre']),
                'proveedor_tipo_documento' => $data['proveedor_tipo_documento'],
                'proveedor_num_documento' => trim($data['proveedor_num_documento']),
                'proveedor_nrc' => DteConfig::digits($data['proveedor_nrc'] ?? '') ?: null,
                'proveedor_cod_actividad' => trim((string) ($data['proveedor_cod_actividad'] ?? '')) ?: null,
                'proveedor_nombre_comercial' => trim((string) ($data['proveedor_nombre_comercial'] ?? '')) ?: null,
                'proveedor_departamento' => $data['proveedor_departamento'],
                'proveedor_municipio' => $data['proveedor_municipio'],
                'proveedor_distrito' => $data['proveedor_distrito'],
                'proveedor_direccion' => trim($data['proveedor_direccion']),
                'proveedor_telefono' => trim((string) ($data['proveedor_telefono'] ?? '')) ?: null,
                'proveedor_correo' => trim((string) ($data['proveedor_correo'] ?? '')) ?: null,
                'retener_renta' => $kind === PurchaseDocument::FSE && ! empty($data['retener_renta']),
                'condicion_operacion' => (int) ($data['condicion_operacion'] ?? 1),
                'forma_pago' => $data['forma_pago'] ?? null,
                'items' => $items,
                'total' => $this->total($kind, $items, ! empty($data['retener_renta'])),
                'observaciones' => trim((string) ($data['observaciones'] ?? '')) ?: null,
                'created_by' => $userId,
            ]);
        });
    }

    /** FSE: lo que se paga (tras la renta retenida). CR: el IVA retenido. */
    private function total(string $kind, array $items, bool $retenerRenta): float
    {
        if ($kind === PurchaseDocument::CR) {
            return array_sum(array_map(fn ($i) => DteParts::cents($i['iva_retenido']), $items)) / 100;
        }

        $compra = array_sum(array_map(fn ($i) => DteParts::cents($i['unit_price']) * $i['quantity'] - DteParts::cents($i['descuento']), $items));

        return ($compra - ($retenerRenta ? (int) round($compra * SujetoExcluidoBuilder::RETENCION_RENTA) : 0)) / 100;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<string>
     */
    public function errors(string $kind, array $d): array
    {
        $errors = [];
        $tipoDoc = (string) ($d['proveedor_tipo_documento'] ?? '');
        $numDoc = trim((string) ($d['proveedor_num_documento'] ?? ''));

        if (trim((string) ($d['proveedor_nombre'] ?? '')) === '') {
            $errors[] = 'Falta el nombre del proveedor.';
        }
        if (! SvCatalogs::validTipoDocumento($tipoDoc) || $numDoc === '') {
            $errors[] = 'Faltan el tipo (CAT-022) o el número de documento del proveedor.';
        } elseif ($tipoDoc === '13' && strlen(DteConfig::digits($numDoc)) !== 9) {
            $errors[] = 'El DUI del proveedor debe tener 9 dígitos.';
        } elseif ($tipoDoc === '36' && ! preg_match('/^([0-9]{14}|[0-9]{9})$/', DteConfig::digits($numDoc))) {
            $errors[] = 'El NIT del proveedor debe tener 9 o 14 dígitos.';
        }
        $errors = [...$errors, ...DteParts::direccionErrors(
            'del proveedor', $d['proveedor_departamento'] ?? null, $d['proveedor_municipio'] ?? null,
            $d['proveedor_distrito'] ?? null, $d['proveedor_direccion'] ?? null,
        )];
        if (($correo = trim((string) ($d['proveedor_correo'] ?? ''))) !== '' && ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo del proveedor no es válido.';
        }

        $items = (array) ($d['items'] ?? []);
        if ($items === []) {
            $errors[] = 'Agrega al menos una línea.';
        }

        return $kind === PurchaseDocument::CR
            ? [...$errors, ...$this->retencionErrors($d, $items)]
            : [...$errors, ...$this->sujetoExcluidoErrors($d, $items)];
    }

    /** @return list<string> */
    private function sujetoExcluidoErrors(array $d, array $items): array
    {
        $errors = [];
        if (($d['proveedor_cod_actividad'] ?? '') !== '' && isset($d['proveedor_cod_actividad'])
            && SvCatalogs::actividad($d['proveedor_cod_actividad']) === null) {
            $errors[] = 'La actividad económica del proveedor no está en el catálogo CAT-019.';
        }
        if (! in_array((int) ($d['condicion_operacion'] ?? 1), [1, 2], true)) {
            $errors[] = 'Condición de la operación: 1 contado o 2 a crédito.';
        }
        foreach ($items as $n => $i) {
            $bruto = (float) ($i['unit_price'] ?? 0) * max((int) ($i['quantity'] ?? 1), 1);
            if (trim((string) ($i['description'] ?? '')) === '' || $bruto <= 0) {
                $errors[] = 'Línea '.($n + 1).': falta la descripción o el precio.';
            } elseif ((float) ($i['descuento'] ?? 0) < 0 || (float) ($i['descuento'] ?? 0) > $bruto) {
                $errors[] = 'Línea '.($n + 1).': el descuento no puede superar el importe.';
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private function retencionErrors(array $d, array $items): array
    {
        $errors = [];
        if (! DteConfig::load()->agenteRetencion()) {
            $errors[] = 'Solo un agente de retención designado por Hacienda emite comprobantes de retención (actívalo en Ajustes).';
        }
        if (($d['proveedor_tipo_documento'] ?? '') !== '36') {
            $errors[] = 'El proveedor de un comprobante de retención es un contribuyente: identifícalo por su NIT.';
        }
        if (! preg_match('/^[0-9]{2,8}$/', DteConfig::digits($d['proveedor_nrc'] ?? ''))) {
            $errors[] = 'El NRC del proveedor debe tener entre 2 y 8 dígitos.';
        }
        if (SvCatalogs::actividad($d['proveedor_cod_actividad'] ?? null) === null) {
            $errors[] = 'La actividad económica del proveedor no está en el catálogo CAT-019.';
        }
        foreach ($items as $n => $i) {
            $linea = 'Línea '.($n + 1).': ';
            if (! in_array($i['tipo_dte'] ?? null, ['01', '03', '14'], true)) {
                $errors[] = $linea.'el documento retenido debe ser una factura (01), un CCF (03) o una factura de sujeto excluido (14).';
            }
            $generacion = (int) ($i['tipo_generacion'] ?? 0);
            $numero = strtoupper(trim((string) ($i['numero_documento'] ?? '')));
            if (! in_array($generacion, [1, 2], true)) {
                $errors[] = $linea.'tipo de generación: 1 físico o 2 electrónico.';
            } elseif ($generacion === 2 && ! preg_match('/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/', $numero)) {
                $errors[] = $linea.'un documento electrónico se identifica por su código de generación.';
            } elseif ($numero === '') {
                $errors[] = $linea.'falta el número del documento.';
            }
            $fecha = rescue(fn () => Carbon::parse((string) ($i['fecha_emision'] ?? '')), null, false);
            if (! $fecha || $fecha->isFuture()) {
                $errors[] = $linea.'la fecha de emisión del documento no es válida.';
            }
            if ((float) ($i['monto_sujeto'] ?? 0) <= 0) {
                $errors[] = $linea.'el monto sujeto a retención debe ser mayor que cero.';
            }
            if (! array_key_exists($i['codigo_retencion'] ?? '', RetencionBuilder::CODIGOS)) {
                $errors[] = $linea.'código de retención: 22 (1 %), C4 (13 %) o C9 (otros).';
            } elseif ($i['codigo_retencion'] === 'C9' && (float) ($i['iva_retenido'] ?? 0) <= 0) {
                $errors[] = $linea.'con C9 hay que indicar el IVA retenido.';
            }
        }

        return $errors;
    }
}
