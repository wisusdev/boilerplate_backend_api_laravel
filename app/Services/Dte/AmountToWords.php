<?php

namespace App\Services\Dte;

/**
 * Total en letras como lo muestra la representación gráfica:
 * "DOS MIL DOSCIENTOS SESENTA DÓLARES CON 50/100".
 */
final class AmountToWords
{
    private const UNIDADES = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS',
        'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];

    private const DECENAS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];

    private const CENTENAS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convert(int $cents): string
    {
        $whole = intdiv($cents, 100);
        $frac = $cents % 100;

        if ($whole === 1) {
            $words = 'UN';
            $unit = 'DÓLAR';
        } else {
            $unit = 'DÓLARES';
            $words = self::intToWords($whole);
            // "UN MILLÓN DE DÓLARES"
            $words = ($whole > 0 && $whole % 1_000_000 === 0) ? $words.' DE' : self::apocope($words);
        }

        return sprintf('%s %s CON %02d/100', $words, $unit, $frac);
    }

    private static function intToWords(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }

        $parts = [];
        $millions = intdiv($n, 1_000_000);
        $thousands = intdiv($n, 1000) % 1000;
        $rest = $n % 1000;

        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'UN MILLÓN' : self::apocope(self::intToWords($millions)).' MILLONES';
        }
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'MIL' : self::apocope(self::belowThousand($thousands)).' MIL';
        }
        if ($rest > 0) {
            $parts[] = self::belowThousand($rest);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $parts = [];
        if (($c = intdiv($n, 100)) > 0) {
            $parts[] = self::CENTENAS[$c];
        }
        $r = $n % 100;
        if ($r > 0 && $r < 30) {
            $parts[] = self::UNIDADES[$r];
        } elseif ($r >= 30) {
            $parts[] = self::DECENAS[intdiv($r, 10)].($r % 10 > 0 ? ' Y '.self::UNIDADES[$r % 10] : '');
        }

        return implode(' ', $parts);
    }

    /** "UNO" se apocopa delante de un sustantivo: "VEINTIÚN MIL", "UN DÓLAR". */
    private static function apocope(string $s): string
    {
        if (str_ends_with($s, 'VEINTIUNO')) {
            return substr($s, 0, -strlen('VEINTIUNO')).'VEINTIÚN';
        }
        if (str_ends_with($s, 'UNO')) {
            return substr($s, 0, -3).'UN';
        }

        return $s;
    }
}
