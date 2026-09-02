<?php

namespace Tests\Unit\Support;

use App\Support\BankStatementCsv;
use Tests\TestCase;

/**
 * No conocemos el formato real que exporta el portal de BAC, así que este
 * parser detecta columnas por nombre y tolera los estilos de fecha/importe más
 * comunes en la banca centroamericana. Lo que se prueba aquí es precisamente lo
 * que más fácil es acertar "a ojo" y equivocarse en producción: separador de
 * miles vs. decimal, y el orden día/mes.
 */
class BankStatementCsvTest extends TestCase
{
    public function test_lee_un_csv_con_encabezados_en_espanol(): void
    {
        $csv = "Fecha,Descripcion,Monto\n01/09/2026,pago CA-000014-9N9G,65.00\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertCount(1, $filas);
        $this->assertSame('2026-09-01', $filas[0]['fecha']->toDateString());
        $this->assertSame('pago CA-000014-9N9G', $filas[0]['descripcion']);
        $this->assertSame(65.0, $filas[0]['monto']);
    }

    public function test_detecta_el_separador_punto_y_coma(): void
    {
        // Habitual cuando el propio CSV usa coma como separador decimal.
        $csv = "Fecha;Concepto;Importe\n02/09/2026;abono CA-000020-AAAA;1.234,56\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertSame(1234.56, $filas[0]['monto']);
    }

    public function test_distingue_miles_en_ingles_de_decimal_en_espanol(): void
    {
        $csv = "Fecha,Importe\n01/09/2026,\"1,234.56\"\n02/09/2026,\"1.234,56\"\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertSame(1234.56, $filas[0]['monto']);
        $this->assertSame(1234.56, $filas[1]['monto']);
    }

    public function test_una_coma_sola_se_interpreta_como_decimal_no_como_miles(): void
    {
        // El valor va entre comillas: es una sola columna CSV que contiene una coma.
        $csv = "Fecha,Importe\n01/09/2026,\"65,50\"\n";

        // Con una sola coma y dos dígitos detrás, es decimal (65.50), no miles.
        $filas = BankStatementCsv::parse($csv);

        $this->assertSame(65.5, $filas[0]['monto']);
    }

    public function test_interpreta_la_fecha_como_dia_mes_ano_no_mes_dia(): void
    {
        // 03/04/2026 en formato centroamericano es 3 de abril, no 4 de marzo.
        $csv = "Fecha,Importe\n03/04/2026,10.00\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertSame('2026-04-03', $filas[0]['fecha']->toDateString());
    }

    public function test_ignora_filas_sin_fecha_o_con_importe_cero_o_negativo(): void
    {
        $csv = "Fecha,Importe\n01/09/2026,65.00\n,50.00\n02/09/2026,0\n03/09/2026,-20.00\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertCount(1, $filas);
    }

    public function test_quita_el_bom_utf8_del_encabezado(): void
    {
        $csv = "\xEF\xBB\xBFFecha,Importe\n01/09/2026,65.00\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertCount(1, $filas);
    }

    public function test_reconoce_columnas_en_ingles_y_sinonimos_de_abono(): void
    {
        $csv = "Date,Memo,Deposito\n01/09/2026,payment,65.00\n";

        $filas = BankStatementCsv::parse($csv);

        $this->assertSame(65.0, $filas[0]['monto']);
        $this->assertSame('payment', $filas[0]['descripcion']);
    }

    public function test_falla_con_un_mensaje_claro_si_no_reconoce_las_columnas(): void
    {
        $csv = "Columna1,Columna2\nx,y\n";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no reconozco/i');

        BankStatementCsv::parse($csv);
    }

    public function test_falla_si_el_archivo_no_tiene_filas_de_datos(): void
    {
        $csv = "Fecha,Importe\n";

        $this->expectException(\InvalidArgumentException::class);

        BankStatementCsv::parse($csv);
    }
}
