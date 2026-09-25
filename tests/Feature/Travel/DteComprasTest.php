<?php

namespace Tests\Feature\Travel;

use App\Models\DteInvalidacion;
use App\Models\PurchaseDocument;
use App\Services\Dte\DteRepresentation;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Documentos que vamosPues emite al comprar: factura de sujeto excluido (14) y
 * comprobante de retención (07), contra sus esquemas oficiales.
 */
class DteComprasTest extends DteTestCase
{
    private const CCF_PROVEEDOR = '5C2B1A00-1111-4222-8333-444455556666';

    private function proveedor(array $extra = []): array
    {
        return array_merge([
            'proveedor_nombre' => 'José Ramírez',
            'proveedor_tipo_documento' => '13',
            'proveedor_num_documento' => '012345678',
            'proveedor_departamento' => '06',
            'proveedor_municipio' => '23',
            'proveedor_distrito' => '14',
            'proveedor_direccion' => 'Col. Centroamérica, pasaje 3',
            'proveedor_correo' => 'jose@guia.test',
        ], $extra);
    }

    private function crear(string $kind, array $attrs): TestResponse
    {
        $this->admin();

        return $this->apiJson('POST', '/api/v1/purchase-documents', ['data' => [
            'type' => 'purchase-documents',
            'attributes' => ['kind' => $kind, ...$attrs],
        ]]);
    }

    private function sujetoExcluido(array $extra = []): TestResponse
    {
        return $this->crear('fse', $this->proveedor([
            'retener_renta' => true,
            'forma_pago' => '01',
            'items' => [
                ['description' => 'Guiado Volcán de Izalco', 'quantity' => 2, 'unit_price' => 25, 'tipo_item' => 2],
                ['description' => 'Agua embotellada', 'quantity' => 1, 'unit_price' => 10, 'descuento' => 2, 'tipo_item' => 1],
            ],
            ...$extra,
        ]));
    }

    private function retencion(array $extra = [], ?array $items = null): TestResponse
    {
        return $this->crear('cr', [
            'proveedor_nombre' => 'TRANSPORTES DEL PACÍFICO, S.A. DE C.V.',
            'proveedor_tipo_documento' => '36',
            'proveedor_num_documento' => '0614-120190-101-1',
            'proveedor_nrc' => '765432',
            'proveedor_cod_actividad' => '49225',
            'proveedor_departamento' => '06',
            'proveedor_municipio' => '23',
            'proveedor_distrito' => '14',
            'proveedor_direccion' => 'Blvd. Los Próceres #20',
            'proveedor_correo' => 'facturas@pacifico.test',
            'items' => $items ?? [
                ['tipo_dte' => '03', 'tipo_generacion' => 2, 'numero_documento' => strtolower(self::CCF_PROVEEDOR),
                    'fecha_emision' => '2026-09-20', 'monto_sujeto' => 200, 'codigo_retencion' => '22'],
                ['tipo_dte' => '03', 'tipo_generacion' => 1, 'numero_documento' => 'CCF-001234',
                    'fecha_emision' => '2026-09-18', 'monto_sujeto' => 100, 'codigo_retencion' => 'C4',
                    'descripcion' => 'Servicio de bus'],
            ],
            ...$extra,
        ]);
    }

    private function dteDe(PurchaseDocument $doc): array
    {
        return $doc->dteDocuments()->latest('id')->firstOrFail()->document();
    }

    // ─── Sujeto excluido ──────────────────────────────────────────────────────

    public function test_la_factura_de_sujeto_excluido_cumple_su_esquema(): void
    {
        $this->sujetoExcluido()->assertCreated()
            ->assertJsonPath('data.attributes.number', 'FSE-00001')
            ->assertJsonPath('data.attributes.dte_status', 'accepted');

        $json = $this->dteDe(PurchaseDocument::firstOrFail());
        $this->assertCumpleEsquema($json, 'fe-fse-v2.json');

        $this->assertSame('14', $json['identificacion']['tipoDte']);
        $this->assertSame('DTE-14-M001P001-000000000000001', $json['identificacion']['numeroControl']);
        $this->assertArrayNotHasKey('nombreComercial', $json['emisor']);
        // El proveedor es el receptor, con su DUI en formato.
        $this->assertSame('13', $json['receptor']['tipoDocumento']);
        $this->assertSame('01234567-8', $json['receptor']['numDocumento']);
        $this->assertSame(2, $json['cuerpoDocumento'][0]['tipoItem']);
        $this->assertSame(99, $json['cuerpoDocumento'][0]['uniMedida']);
        $this->assertSame(1, $json['cuerpoDocumento'][1]['tipoItem']);
        $this->assertEquals(8, $json['cuerpoDocumento'][1]['compra']);    // 10 − 2
        $this->assertEquals(58, $json['resumen']['totalCompra']);
        $this->assertEquals(5.80, $json['resumen']['reteRenta']);        // 10 % de renta
        $this->assertEquals(52.20, $json['resumen']['totalPagar']);
        $this->assertEquals(52.20, $json['resumen']['pagos'][0]['montoPago']);
        $this->assertSame('14', $this->llamadas('/fesv/recepciondte')[0]['tipoDte']);
        $this->assertEquals('52.20', PurchaseDocument::firstOrFail()->total);
    }

    public function test_a_credito_no_lleva_pagos(): void
    {
        $this->sujetoExcluido(['condicion_operacion' => 2, 'retener_renta' => false])->assertCreated();

        $json = $this->dteDe(PurchaseDocument::firstOrFail());
        $this->assertCumpleEsquema($json, 'fe-fse-v2.json');
        $this->assertSame(2, $json['resumen']['condicionOperacion']);
        $this->assertNull($json['resumen']['pagos']);
        $this->assertEquals(0, $json['resumen']['reteRenta']);
    }

    public function test_un_proveedor_incompleto_no_se_guarda(): void
    {
        $errores = $this->crear('fse', [
            'proveedor_tipo_documento' => '13',
            'proveedor_num_documento' => '123',
            'items' => [['description' => '', 'quantity' => 1, 'unit_price' => 0]],
        ])->assertStatus(422)->json('errors.*.detail');

        $this->assertContains('Falta el nombre del proveedor.', $errores);
        $this->assertContains('El DUI del proveedor debe tener 9 dígitos.', $errores);
        $this->assertContains('El departamento del proveedor no es válido (CAT-012).', $errores);
        $this->assertContains('Línea 1: falta la descripción o el precio.', $errores);
        $this->assertSame(0, PurchaseDocument::count());
    }

    public function test_la_representacion_y_la_invalidacion_del_sujeto_excluido(): void
    {
        $this->sujetoExcluido()->assertCreated();
        $doc = PurchaseDocument::firstOrFail();
        $dte = $doc->dteDocuments()->firstOrFail();

        $data = app(DteRepresentation::class)->data($dte);
        $this->assertSame('FACTURA DE SUJETO EXCLUIDO', $data['titulo']);
        $this->assertSame('SUJETO EXCLUIDO', $data['receptorTitulo']);
        $this->assertFalse($data['seccionesD']);
        $this->assertSame('Compra', end($data['tabla']['cols'])[0]);
        $this->assertEquals(5.80, array_column($data['totales'], 1, 0)['Retención de renta']);
        $this->get("/api/v1/dte/documents/{$dte->id}/pdf")->assertOk();

        $this->apiJson('POST', "/api/v1/purchase-documents/{$doc->id}/invalidate-dte", ['data' => [
            'type' => 'dte-invalidations',
            'attributes' => ['tipo_anulacion' => 2, 'solicita_nombre' => 'José Ramírez', 'solicita_tipo_doc' => '13', 'solicita_num_doc' => '01234567-8'],
        ]])->assertCreated();

        $evento = json_decode(DteInvalidacion::firstOrFail()->json_content, true);
        $this->assertCumpleEsquema($evento, 'invalidacion-schema-v3.json');
        $this->assertSame('14', $evento['documento']['tipoDte']);
        $this->assertSame('invalidated', $doc->fresh()->dte_status);
    }

    public function test_con_el_mh_caido_el_sujeto_excluido_sale_en_contingencia(): void
    {
        $this->recepciones = [500, 500];
        $this->sujetoExcluido()->assertStatus(202)->assertJsonPath('data.attributes.dte_status', 'contingency');

        $json = $this->dteDe(PurchaseDocument::firstOrFail());
        $this->assertCumpleEsquema($json, 'fe-fse-v2.json');
        $this->assertSame(2, $json['identificacion']['tipoModelo']);
    }

    // ─── Comprobante de retención ─────────────────────────────────────────────

    public function test_solo_un_agente_de_retencion_emite_comprobantes(): void
    {
        $this->retencion()->assertStatus(422)->assertJsonFragment([
            'detail' => 'Solo un agente de retención designado por Hacienda emite comprobantes de retención (actívalo en Ajustes).',
        ]);
    }

    public function test_el_comprobante_de_retencion_cumple_su_esquema(): void
    {
        $this->configurar(['dte_agente_retencion' => true]);

        $this->retencion()->assertCreated()->assertJsonPath('data.attributes.number', 'CR-00001');

        $json = $this->dteDe(PurchaseDocument::firstOrFail());
        $this->assertCumpleEsquema($json, 'fe-cr-v2.json');

        $this->assertSame('07', $json['identificacion']['tipoDte']);
        $this->assertSame('DTE-07-M001P001-000000000000001', $json['identificacion']['numeroControl']);
        $this->assertSame('36', $json['receptor']['tipoDocumento']);
        $this->assertSame('06141201901011', $json['receptor']['numDocumento']);
        $this->assertSame('765432', $json['receptor']['nrc']);

        [$electronico, $fisico] = $json['cuerpoDocumento'];
        $this->assertSame(self::CCF_PROVEEDOR, $electronico['numeroDocumento']); // en mayúsculas
        $this->assertSame(2, $electronico['tipoGeneracion']);
        $this->assertEquals(2.00, $electronico['ivaRetenido']);                 // 22: 1 % de 200
        $this->assertSame('CCF-001234', $fisico['numeroDocumento']);
        $this->assertEquals(13.00, $fisico['ivaRetenido']);                     // C4: 13 % de 100

        $this->assertEquals(300, $json['resumen']['totalSujetoRetencion']);
        $this->assertEquals(39, $json['resumen']['totalIva']);
        $this->assertEquals(15, $json['resumen']['totalIvaRetenido']);
        $this->assertSame('QUINCE DÓLARES CON 00/100', $json['resumen']['totalLetras']);

        $data = app(DteRepresentation::class)->data(PurchaseDocument::firstOrFail()->dteDocuments()->firstOrFail());
        $this->assertSame('COMPROBANTE DE RETENCIÓN', $data['titulo']);
        $this->assertNull($data['condicion']);
    }

    public function test_el_comprobante_de_retencion_valida_cada_documento(): void
    {
        $this->configurar(['dte_agente_retencion' => true]);

        $errores = $this->retencion(['proveedor_tipo_documento' => '13', 'proveedor_nrc' => null], [
            ['tipo_dte' => '03', 'tipo_generacion' => 2, 'numero_documento' => 'no-es-un-codigo',
                'fecha_emision' => '2026-09-20', 'monto_sujeto' => 200, 'codigo_retencion' => '22'],
            ['tipo_dte' => '03', 'tipo_generacion' => 1, 'numero_documento' => '555',
                'fecha_emision' => '2030-01-01', 'monto_sujeto' => 100, 'codigo_retencion' => 'C9'],
        ])->assertStatus(422)->json('errors.*.detail');

        $this->assertContains('El proveedor de un comprobante de retención es un contribuyente: identifícalo por su NIT.', $errores);
        $this->assertContains('El NRC del proveedor debe tener entre 2 y 8 dígitos.', $errores);
        $this->assertContains('Línea 1: un documento electrónico se identifica por su código de generación.', $errores);
        $this->assertContains('Línea 2: la fecha de emisión del documento no es válida.', $errores);
        $this->assertContains('Línea 2: con C9 hay que indicar el IVA retenido.', $errores);
    }

    public function test_el_listado_filtra_por_tipo(): void
    {
        $this->configurar(['dte_agente_retencion' => true]);
        $this->sujetoExcluido()->assertCreated();
        $this->retencion()->assertCreated();

        $this->apiJson('GET', '/api/v1/purchase-documents?kind=cr')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.number', 'CR-00001');
    }

    public function test_con_el_dte_desactivado_no_se_guarda_el_documento(): void
    {
        $this->configurar(['dte_enabled' => false, 'dte_agente_retencion' => true]);

        $this->sujetoExcluido()->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'La facturación electrónica no está habilitada en la configuración.');
        $this->retencion()->assertStatus(422);
        $this->assertSame(0, PurchaseDocument::count());
        Http::assertNothingSent();
    }

    public function test_el_panel_consulta_si_el_dte_esta_activo(): void
    {
        $this->configurar(['dte_agente_retencion' => true]);
        $this->admin();

        $this->apiJson('GET', '/api/v1/dte/status')->assertOk()
            ->assertJsonPath('data.attributes.enabled', true)
            ->assertJsonPath('data.attributes.ambiente', '00')
            ->assertJsonPath('data.attributes.agente_retencion', true);

        $this->configurar(['dte_enabled' => false]);
        $this->apiJson('GET', '/api/v1/dte/status')->assertJsonPath('data.attributes.enabled', false);
    }
}
