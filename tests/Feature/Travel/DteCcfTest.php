<?php

namespace Tests\Feature\Travel;

use App\Models\DteInvalidacion;
use App\Models\Invoice;
use App\Services\Dte\DteRepresentation;

/**
 * Comprobante de Crédito Fiscal (03, esquema v4): precios sin IVA, IVA como
 * tributo 20, receptor contribuyente completo y retención o percepción del 1 %.
 */
class DteCcfTest extends DteTestCase
{
    /** Cliente contribuyente con todos sus datos en códigos de catálogo. */
    private function clienteCcf(array $extra = []): array
    {
        return array_merge([
            'dte_type' => '03',
            'receptor_name' => 'AGENCIA DE VIAJES EL SOL, S.A. DE C.V.',
            'receptor_document' => '0614-250190-102-3',
            'receptor_nrc' => '12345-6',
            'receptor_cod_actividad' => '79110',
            'receptor_nombre_comercial' => 'Viajes El Sol',
            'receptor_departamento' => '06',
            'receptor_municipio' => '23',
            'receptor_distrito' => '14',
            'receptor_direccion' => 'Av. Olímpica #100',
            'receptor_telefono' => '22334455',
            'receptor_email' => 'compras@elsol.test',
        ], $extra);
    }

    private function ccf(array $extra = [], ?array $items = null): Invoice
    {
        return $this->facturaManual($items, $this->clienteCcf($extra));
    }

    public function test_el_ccf_cumple_su_esquema_con_precios_sin_iva(): void
    {
        $invoice = $this->ccf();
        $this->emitir($invoice)->assertOk();

        $doc = $this->documento($invoice);
        $this->assertCumpleEsquema($doc, 'fe-ccf-v4.json');

        $this->assertSame('03', $doc['identificacion']['tipoDte']);
        $this->assertSame(4, $doc['identificacion']['version']);
        // Su propio correlativo, aparte del de las facturas.
        $this->assertSame('DTE-03-M001P001-000000000000001', $doc['identificacion']['numeroControl']);

        // Sin IVA: lo cobrado entre 1.13, y el IVA como tributo 20.
        $this->assertEquals(10.00, $doc['cuerpoDocumento'][0]['ventaGravada']);
        $this->assertEquals(115.04, $doc['cuerpoDocumento'][1]['ventaGravada']);
        $this->assertEquals(57.52, $doc['cuerpoDocumento'][1]['precioUni']);
        $this->assertSame(['20'], $doc['cuerpoDocumento'][0]['tributos']);
        $this->assertArrayNotHasKey('ivaItem', $doc['cuerpoDocumento'][0]);
        $this->assertEquals(125.04, $doc['resumen']['totalGravada']);
        $this->assertEquals([['codigo' => '20', 'descripcion' => 'Impuesto al Valor Agregado 13%', 'valor' => 16.26]], $doc['resumen']['tributos']);
        $this->assertEquals(141.30, $doc['resumen']['montoTotalOperacion']);
        $this->assertEquals(141.30, $doc['resumen']['totalPagar']);
        $this->assertEquals(0, $doc['resumen']['ivaRete']);
        $this->assertEquals(0, $doc['resumen']['ivaPerci']);
        $this->assertArrayNotHasKey('totalIva', $doc['resumen']);

        // Receptor contribuyente, sin guiones y con la actividad del catálogo.
        $this->assertSame('06142501901023', $doc['receptor']['nit']);
        $this->assertSame('123456', $doc['receptor']['nrc']);
        $this->assertStringStartsWith('Actividades de agencias de viajes', $doc['receptor']['descActividad']);
        $this->assertSame(['departamento' => '06', 'municipio' => '23', 'distrito' => '14', 'complemento' => 'Av. Olímpica #100'], $doc['receptor']['direccion']);

        $this->assertSame('03', $this->llamadas('/fesv/recepciondte')[0]['tipoDte']);
        $this->assertSame(4, $this->llamadas('/fesv/recepciondte')[0]['version']);
    }

    public function test_un_ccf_con_el_cliente_incompleto_no_se_guarda(): void
    {
        $this->admin();
        $errores = $this->apiJson('POST', '/api/v1/invoices', ['data' => ['type' => 'invoices', 'attributes' => [
            ...$this->clienteCcf(['receptor_nrc' => null, 'receptor_distrito' => '99']),
            'items' => [['description' => 'Tour', 'quantity' => 1, 'unit_price' => 100]],
        ]]])->assertStatus(422)->json('errors.*.detail');

        $this->assertContains('El NRC del cliente debe tener entre 2 y 8 dígitos.', $errores);
        $this->assertContains('El distrito del cliente no pertenece al municipio (CAT-008).', $errores);
        $this->assertSame(0, Invoice::count());
    }

    public function test_el_cliente_agente_de_retencion_retiene_el_uno_por_ciento(): void
    {
        $this->configurar(['dte_retencion_activa' => true]);
        $invoice = $this->ccf(['receptor_agente_retencion' => true]);
        $this->emitir($invoice)->assertOk();

        $doc = $this->documento($invoice);
        $this->assertCumpleEsquema($doc, 'fe-ccf-v4.json');
        $this->assertEquals(1.25, $doc['resumen']['ivaRete']);      // 1 % de 125.04
        $this->assertEquals(0, $doc['resumen']['ivaPerci']);        // nunca las dos
        $this->assertEquals(140.05, $doc['resumen']['totalPagar']); // 141.30 − 1.25
        $this->assertEquals(140.05, array_sum(array_column($doc['resumen']['pagos'], 'montoPago')));
        $this->assertSame('CIENTO CUARENTA DÓLARES CON 05/100', $doc['resumen']['totalLetras']);
    }

    public function test_vamospues_agente_de_percepcion_cobra_el_uno_por_ciento(): void
    {
        $this->configurar(['dte_percepcion_activa' => true]);
        $invoice = $this->ccf();
        $this->emitir($invoice)->assertOk();

        $doc = $this->documento($invoice);
        $this->assertCumpleEsquema($doc, 'fe-ccf-v4.json');
        $this->assertEquals(1.25, $doc['resumen']['ivaPerci']);
        $this->assertEquals(142.55, $doc['resumen']['totalPagar']);
    }

    public function test_bajo_el_minimo_no_hay_retencion_ni_percepcion(): void
    {
        $this->configurar(['dte_percepcion_activa' => true, 'dte_iva_ajuste_minimo' => 200]);
        $invoice = $this->ccf();
        $this->emitir($invoice)->assertOk();

        $this->assertEquals(0, $this->documento($invoice)['resumen']['ivaPerci']);
    }

    public function test_el_ccf_se_invalida_declarando_al_receptor_por_su_nit(): void
    {
        $invoice = $this->ccf();
        $this->emitir($invoice)->assertOk();

        $this->apiJson('POST', "/api/v1/invoices/{$invoice->id}/invalidate-dte", ['data' => [
            'type' => 'dte-invalidations',
            'attributes' => [
                'tipo_anulacion' => 2, 'solicita_nombre' => 'Carlos Pérez',
                'solicita_tipo_doc' => '13', 'solicita_num_doc' => '01234567-8',
            ],
        ]])->assertCreated();

        $evento = json_decode(DteInvalidacion::firstOrFail()->json_content, true);
        $this->assertCumpleEsquema($evento, 'invalidacion-schema-v3.json');
        $this->assertSame('36', $evento['documento']['tipoDocumento']);
        $this->assertSame('06142501901023', $evento['documento']['numDocumento']);
        // En el CCF, la fecha del evento es la de generación del DTE.
        $this->assertSame($this->documento($invoice)['identificacion']['fecEmi'], $evento['identificacion']['fecEmi']);
    }

    public function test_la_representacion_grafica_del_ccf(): void
    {
        $this->configurar(['dte_percepcion_activa' => true]);
        $invoice = $this->ccf();
        $this->emitir($invoice)->assertOk();

        $data = app(DteRepresentation::class)->data($this->dte($invoice));
        $this->assertSame('COMPROBANTE DE CRÉDITO FISCAL', $data['titulo']);
        $this->assertSame('06142501901023', $data['receptor']['NIT']);
        $totales = array_column($data['totales'], 1, 0);
        $this->assertEquals(16.26, $totales['Impuesto al Valor Agregado 13%']);
        $this->assertEquals(1.25, $totales['IVA percibido']);
        $this->assertEquals(142.55, $totales['Total a pagar']);
        $this->assertArrayNotHasKey('IVA incluido en ventas gravadas (13%)', $totales);
    }
}
