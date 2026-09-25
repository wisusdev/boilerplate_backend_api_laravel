<?php

namespace App\Support\Dte;

/**
 * Catálogos del Sistema de Transmisión del Ministerio de Hacienda (v1.1).
 *
 * `resources/dte/catalogs_sv.json` se generó del Excel oficial de
 * factura.gob.sv y es la única fuente de verdad de estos códigos: un
 * departamento enviado por nombre o una actividad que no está en CAT-019 hacen
 * que el MH rechace el documento.
 *
 * La relación distrito → municipio no viene en CAT-008; sale del Decreto
 * Legislativo 762 (2023: 262 distritos en 44 municipios).
 */
class SvCatalogs
{
    private static ?array $all = null;

    private static ?array $actividades = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$all ??= json_decode(
            file_get_contents(resource_path('dte/catalogs_sv.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** CAT-012. "00" (extranjero) no vale para un establecimiento en El Salvador. */
    public static function validDepartamento(?string $code): bool
    {
        return $code !== '00' && self::contains(self::all()['departamentos'], $code);
    }

    /** CAT-013. Los códigos se repiten entre departamentos: la clave es el par. */
    public static function validMunicipio(?string $dep, ?string $mun): bool
    {
        return self::contains(self::all()['municipios'][$dep] ?? [], $mun);
    }

    /** CAT-008, y que el distrito pertenezca a ese municipio. */
    public static function validDistrito(?string $dep, ?string $mun, ?string $dis): bool
    {
        return self::contains(self::all()['distritos'][$dep] ?? [], $dis)
            && (self::all()['distrito_municipio'][$dep][$dis] ?? null) === $mun;
    }

    /** Descripción CAT-019 de una actividad económica, o null si no existe. */
    public static function actividad(?string $code): ?string
    {
        self::$actividades ??= array_column(self::all()['actividades'], 'name', 'code');

        return self::$actividades[trim((string) $code)] ?? null;
    }

    public static function validTipoEstablecimiento(?string $code): bool
    {
        return self::contains(self::all()['tipos_establecimiento'], $code);
    }

    public static function validTipoDocumento(?string $code): bool
    {
        return self::contains(self::all()['tipos_documento'], $code);
    }

    /**
     * Letra del tipo de establecimiento en el número de control (Manual
     * Funcional v2): M casa matriz, S sucursal, B bodega, P patio.
     */
    public static function establecimientoLetter(?string $tipo): string
    {
        return match ($tipo) {
            '01' => 'S',
            '04' => 'B',
            '07' => 'P',
            default => 'M',
        };
    }

    /**
     * Lo que necesitan los formularios del back-office.
     *
     * @return array<string, mixed>
     */
    public static function forForms(): array
    {
        $all = self::all();

        return [
            'departamentos' => array_values(array_filter($all['departamentos'], fn ($d) => $d['code'] !== '00')),
            'municipios' => $all['municipios'],
            'distritos' => $all['distritos'],
            'distrito_municipio' => $all['distrito_municipio'],
            'actividades' => $all['actividades'],
            'tipos_establecimiento' => $all['tipos_establecimiento'],
            'tipos_documento' => $all['tipos_documento'],
        ];
    }

    /** @param  array<int, array{code: string, name: string}>  $list */
    private static function contains(array $list, ?string $code): bool
    {
        return $code !== null && in_array($code, array_column($list, 'code'), true);
    }
}
