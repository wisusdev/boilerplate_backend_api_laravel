<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Lee el CSV exportado del portal del banco.
 *
 * No conocemos el formato real de BAC: la propuesta original (PAGO-ENLACE-BAC.md
 * §2) ya avisaba de que esta pieza habría que rediseñarla en cuanto viéramos un
 * export de verdad. Mientras tanto, detecta las columnas por nombre en vez de
 * asumir un orden fijo, y admite los formatos de fecha e importe más comunes en
 * la banca centroamericana (coma decimal, separador `;`, etc.).
 *
 * Deliberadamente NO adivina en silencio lo que no reconoce: si no encuentra
 * columna de fecha o de importe, falla con un mensaje que dice qué encabezados
 * sí vio, para que quien lo use pueda decirnos cómo ajustar esto.
 */
class BankStatementCsv
{
    private const COLUMNAS_FECHA = ['fecha', 'fecha transaccion', 'fecha de transaccion', 'transaction date', 'date'];

    private const COLUMNAS_DESCRIPCION = ['descripcion', 'concepto', 'referencia', 'detalle', 'description', 'memo', 'glosa'];

    private const COLUMNAS_IMPORTE = [
        'monto', 'importe', 'valor', 'amount', 'abono', 'deposito', 'credito', 'monto abono', 'monto credito',
    ];

    /**
     * @return array<int, array{fecha: Carbon, descripcion: string, monto: float}>
     */
    public static function parse(string $contenido): array
    {
        $contenido = self::sinBom($contenido);
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $lineas = array_values(array_filter($lineas, fn ($l) => trim($l) !== ''));

        if (count($lineas) < 2) {
            throw new \InvalidArgumentException('El archivo no tiene filas de datos, solo encabezado (o está vacío).');
        }

        $separador = self::detectarSeparador($lineas[0]);
        $encabezado = array_map(fn ($h) => self::normalizar($h), str_getcsv($lineas[0], $separador));

        $colFecha = self::localizarColumna($encabezado, self::COLUMNAS_FECHA);
        $colImporte = self::localizarColumna($encabezado, self::COLUMNAS_IMPORTE);
        $colDescripcion = self::localizarColumna($encabezado, self::COLUMNAS_DESCRIPCION);

        if ($colFecha === null || $colImporte === null) {
            throw new \InvalidArgumentException(
                'No reconozco las columnas de fecha e importe en el encabezado ('
                .implode(', ', $encabezado).'). Renombra esas columnas a algo como '
                .'"Fecha" e "Importe", o pide que se ajuste el lector a este formato.'
            );
        }

        $filas = [];

        foreach (array_slice($lineas, 1) as $linea) {
            $columnas = str_getcsv($linea, $separador);

            $fecha = self::parsearFecha($columnas[$colFecha] ?? '');
            $monto = self::parsearMonto($columnas[$colImporte] ?? '');

            // Filas sin fecha o con importe nulo/negativo no son un cobro: se
            // saltan en vez de reventar la importación completa por una fila rota
            // (totales, líneas en blanco, cargos que no son abonos).
            if ($fecha === null || $monto === null || $monto <= 0) {
                continue;
            }

            $filas[] = [
                'fecha' => $fecha,
                'descripcion' => $colDescripcion !== null ? trim((string) ($columnas[$colDescripcion] ?? '')) : '',
                'monto' => $monto,
            ];
        }

        return $filas;
    }

    private static function sinBom(string $contenido): string
    {
        return str_starts_with($contenido, "\xEF\xBB\xBF") ? substr($contenido, 3) : $contenido;
    }

    /** El `;` es habitual en exports centroamericanos porque el `,` ya se usa como decimal. */
    private static function detectarSeparador(string $cabecera): string
    {
        return substr_count($cabecera, ';') > substr_count($cabecera, ',') ? ';' : ',';
    }

    private static function normalizar(string $texto): string
    {
        $texto = trim(mb_strtolower($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    /** @param array<int, string> $candidatos */
    private static function localizarColumna(array $encabezado, array $candidatos): ?int
    {
        foreach ($candidatos as $candidato) {
            $i = array_search($candidato, $encabezado, true);
            if ($i !== false) {
                return $i;
            }
        }

        // Coincidencia parcial como último recurso: "monto de la transaccion"
        // contiene "monto" aunque no sea un match exacto.
        foreach ($encabezado as $i => $col) {
            foreach ($candidatos as $candidato) {
                if (str_contains($col, $candidato)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function parsearFecha(string $valor): ?Carbon
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        // Se intentan primero los formatos habituales en Centroamérica
        // (día/mes/año) antes del genérico, que interpretaría "03/04/2026" como
        // 3 de abril en vez de 4 de marzo.
        foreach (['d/m/Y H:i', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $valor);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * "1,234.56", "1.234,56" y "65.00" deben dar el mismo número. Se asume que
     * el ÚLTIMO separador que aparece es el decimal (es como se escriben ambos
     * estilos) y el resto son separadores de miles.
     */
    private static function parsearMonto(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        // Símbolos de moneda y espacios no aportan nada al número.
        $valor = preg_replace('/[^0-9.,\-]/', '', $valor) ?? '';
        if ($valor === '' || $valor === '-') {
            return null;
        }

        $ultimaComa = strrpos($valor, ',');
        $ultimoPunto = strrpos($valor, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            // El orden importa: si primero se cambia la coma por punto y LUEGO se
            // quitan los puntos, se borra también el punto decimal recién creado.
            $valor = $ultimaComa > $ultimoPunto
                ? str_replace(',', '.', str_replace('.', '', $valor))   // 1.234,56 → 1234.56
                : str_replace(',', '', $valor);                        // 1,234.56 → 1234.56
        } elseif ($ultimaComa !== false) {
            // Solo coma: decimal si quedan 1-2 dígitos tras ella, si no es de miles.
            $decimales = strlen($valor) - $ultimaComa - 1;
            $valor = $decimales <= 2
                ? str_replace(',', '.', $valor)
                : str_replace(',', '', $valor);
        }

        return is_numeric($valor) ? round((float) $valor, 2) : null;
    }
}
