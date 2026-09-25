<?php

namespace App\Services\Dte;

use App\Support\Dte\SvCatalogs;
use Carbon\CarbonInterface;

/**
 * Piezas comunes a todos los tipos de DTE: identificación, emisor, direcciones
 * y valores opcionales. Cada esquema pide un subconjunto distinto del emisor,
 * así que se arma completo y cada tipo se queda con lo suyo.
 */
final class DteParts
{
    /** Versión del esquema vigente de cada tipo (CAT-002). */
    public const VERSIONES = [
        '01' => 2, // Factura
        '03' => 4, // Comprobante de crédito fiscal
        '05' => 4, // Nota de crédito
        '06' => 4, // Nota de débito
        '07' => 2, // Comprobante de retención
        '14' => 2, // Factura de sujeto excluido
    ];

    public const IVA_RATE = 0.13;

    /** @return array<string, mixed> */
    public static function identificacion(string $tipo, DteConfig $config, string $numeroControl, string $codigo, CarbonInterface $now, bool $fusion = false): array
    {
        $now = $now->copy()->setTimezone('America/El_Salvador');

        $id = [
            'version' => self::VERSIONES[$tipo],
            'ambiente' => $config->ambiente(),
            'tipoDte' => $tipo,
            'numeroControl' => $numeroControl,
            'codigoGeneracion' => $codigo,
            'tipoModelo' => 1,        // CAT-003: modelo previo
            'tipoOperacion' => 1,     // CAT-004: transmisión normal
            'tipoContingencia' => null,
            'motivoContin' => null,
            'fecEmi' => $now->format('Y-m-d'),
            'horEmi' => $now->format('H:i:s'),
            'tipoMoneda' => 'USD',
        ];
        if ($fusion) {
            $id['fusion'] = null; // Notas de crédito y débito, comprobante de retención
        }

        return $id;
    }

    /**
     * El emisor con todos los campos que usa algún esquema. Cada tipo toma
     * los suyos con `only()`: el esquema rechaza cualquier campo de más.
     *
     * @return array<string, mixed>
     */
    public static function emisor(DteConfig $config): array
    {
        return [
            'nit' => $config->nit(),
            'nrc' => $config->nrc(),
            'nombre' => mb_substr($config->nombre(), 0, 250),
            'codActividad' => $config->codActividad(),
            'descActividad' => mb_substr($config->descActividad(), 0, 150),
            'nombreComercial' => self::optional($config->nombreComercial(), 1, 150),
            'direccion' => self::direccion($config->departamento(), $config->municipio(), $config->distrito(), $config->direccion()),
            'telefono' => mb_substr($config->telefono(), 0, 30),
            'correo' => mb_substr($config->correo(), 0, 100),
            'codEstable' => $config->codEstableCompleto(),
            'codPuntoVenta' => $config->codPuntoVentaCompleto(),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys)) + array_fill_keys(array_diff($keys, array_keys($data)), null);
    }

    /** @return array{departamento: string, municipio: string, distrito: string, complemento: string} */
    public static function direccion(?string $dep, ?string $mun, ?string $dis, ?string $complemento): array
    {
        return [
            'departamento' => (string) $dep,
            'municipio' => (string) $mun,
            'distrito' => (string) $dis,
            'complemento' => mb_substr(trim((string) $complemento), 0, 200),
        ];
    }

    /**
     * Problemas de una dirección en códigos de catálogo, para validar antes de
     * numerar. `$del`: "del cliente", "del proveedor"…
     *
     * @return list<string>
     */
    public static function direccionErrors(string $del, ?string $dep, ?string $mun, ?string $dis, ?string $complemento): array
    {
        if (! SvCatalogs::validDepartamento($dep)) {
            return ["El departamento {$del} no es válido (CAT-012)."];
        }
        if (! SvCatalogs::validMunicipio($dep, $mun)) {
            return ["El municipio {$del} no pertenece al departamento (CAT-013)."];
        }
        if (! SvCatalogs::validDistrito($dep, $mun, $dis)) {
            return ["El distrito {$del} no pertenece al municipio (CAT-008)."];
        }
        if (trim((string) $complemento) === '') {
            return ["Falta la dirección {$del}."];
        }

        return [];
    }

    /** null si está vacío o no llega al mínimo del esquema; recortado al máximo. */
    public static function optional(?string $value, int $min, int $max): ?string
    {
        $value = trim((string) $value);

        return mb_strlen($value) < $min ? null : mb_substr($value, 0, $max);
    }

    public static function cents(float|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
