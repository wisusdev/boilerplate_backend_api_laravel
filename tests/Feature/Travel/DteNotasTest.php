<?php

namespace Tests\Feature\Travel;

use App\Models\CreditNote;
use App\Models\DteDocument;
use App\Models\Invoice;
use App\Services\Dte\DteRepresentation;
use Illuminate\Testing\TestResponse;

/**
 * Notas de crédito (05) y débito (06) sobre un CCF sellado, contra sus
 * esquemas oficiales v4.
 */
class DteNotasTest extends DteTestCase
{
    private function ccfSellado(array $extra = []): Invoice
    {
        $invoice = $this->facturaManual(receptor: array_merge([
            'dte_type' => '03',
            'receptor_name' => 'AGENCIA DE VIAJES EL SOL, S.A. DE C.V.',
            'receptor_document' => '0614-250190-102-3',
            'receptor_nrc' => '123456',
            'receptor_cod_actividad' => '79110',
            'receptor_departamento' => '06',
            'receptor_municipio' => '23',
            'receptor_distrito' => '14',
            'receptor_direccion' => 'Av. Olímpica #100',
            'receptor_email' => 'compras@elsol.test',
        ], $extra));
        $this->emitir($invoice)->assertOk();

        return $invoice->fresh(); // totalGravada del CCF: 125.04
    }

    private function nota(Invoice $invoice, string $kind = 'credit', float $precio = 10, int $cantidad = 1): TestResponse
    {
        return $this->apiJson('POST', "/api/v1/invoices/{$invoice->id}/credit-notes", ['data' => [
            'type' => 'credit-notes',
            'attributes' => [
                'kind' => $kind,
                'motivo' => $kind === 'credit' ? 'Descuento no aplicado' : 'Cargo por equipaje extra',
                'items' => [['description' => 'Ajuste de precio', 'quantity' => $cantidad, 'unit_price' => $precio]],
            ],
        ]]);
    }

    private function dteNota(CreditNote $note): DteDocument
    {
        return $note->dteDocuments()->latest('id')->firstOrFail();
    }

    private function invalidar(string $uri, array $attrs = []): TestResponse
    {
        return $this->apiJson('POST', $uri, ['data' => ['type' => 'dte-invalidations', 'attributes' => array_merge([
            'tipo_anulacion' => 2, 'solicita_nombre' => 'Carlos Pérez', 'solicita_tipo_doc' => '13', 'solicita_num_doc' => '01234567-8',
        ], $attrs)]]);
    }

    public function test_la_nota_de_credito_ajusta_el_ccf_segun_su_esquema(): void
    {
        $ccf = $this->ccfSellado();
        $dteCcf = $this->dte($ccf);

        $this->nota($ccf)->assertCreated()
            ->assertJsonPath('data.attributes.number', 'NC-00001')
            ->assertJsonPath('data.attributes.dte_status', Invoice::DTE_ACCEPTED);

        $note = CreditNote::firstOrFail();
        $doc = $this->dteNota($note)->document();
        $this->assertCumpleEsquema($doc, 'fe-nc-v4.json');

        $this->assertSame('05', $doc['identificacion']['tipoDte']);
        $this->assertSame('DTE-05-M001P001-000000000000001', $doc['identificacion']['numeroControl']);
        $this->assertSame([[
            'tipoDocumento' => '03', 'tipoGeneracion' => 2,
            'numeroDocumento' => $dteCcf->codigo_generacion,
            'fechaEmision' => $dteCcf->document()['identificacion']['fecEmi'],
        ]], $doc['documentoRelacionado']);
        $this->assertSame('36', $doc['receptor']['tipoDocumento']);
        $this->assertSame('06142501901023', $doc['receptor']['numDocumento']);
        $this->assertSame($dteCcf->codigo_generacion, $doc['cuerpoDocumento'][0]['numeroDocumento']);
        $this->assertEquals(10, $doc['resumen']['totalGravada']);
        $this->assertEquals(1.30, $doc['resumen']['totalIva']);
        $this->assertEquals(1.30, $doc['resumen']['tributos'][0]['valor']);
        $this->assertEquals(11.30, $doc['resumen']['totalPagar']);
        $this->assertNull($doc['resumen']['codigoRetencionMH']);
        $this->assertArrayNotHasKey('numPagoElectronico', $doc['resumen']);
        $this->assertSame('NC-00001', $doc['apendice'][0]['valor']);
    }

    public function test_la_nota_de_debito_cumple_su_esquema(): void
    {
        $ccf = $this->ccfSellado();

        $this->nota($ccf, 'debit', 25)->assertCreated()->assertJsonPath('data.attributes.number', 'ND-00001');

        $doc = $this->dteNota(CreditNote::firstOrFail())->document();
        $this->assertCumpleEsquema($doc, 'fe-nd-v4.json');
        $this->assertSame('06', $doc['identificacion']['tipoDte']);
        $this->assertSame('DTE-06-M001P001-000000000000001', $doc['identificacion']['numeroControl']);
        $this->assertArrayHasKey('numPagoElectronico', $doc['resumen']);
    }

    public function test_lo_acreditado_no_supera_el_ccf_mas_los_debitos(): void
    {
        $ccf = $this->ccfSellado();

        $this->nota($ccf, 'credit', 126)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'La nota ($126.00) más lo ya acreditado ($0.00) supera el valor del CCF sin IVA ($125.04).');

        // Una nota de débito amplía el tope.
        $this->nota($ccf, 'debit', 10)->assertCreated();
        $this->nota($ccf, 'credit', 130)->assertCreated();
        $this->nota($ccf, 'credit', 6)->assertStatus(422);
    }

    public function test_una_factura_de_consumidor_final_no_se_ajusta_con_notas(): void
    {
        $factura = $this->facturaManual();
        $this->emitir($factura)->assertOk();

        $this->nota($factura)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'Las notas de crédito y débito solo ajustan un comprobante de crédito fiscal con sello. Una factura de consumidor final se corrige invalidándola.');
        $this->assertSame(0, CreditNote::count());
    }

    public function test_la_nota_repite_la_retencion_del_ccf(): void
    {
        $this->configurar(['dte_retencion_activa' => true]);
        $ccf = $this->ccfSellado(['receptor_agente_retencion' => true]);

        $this->nota($ccf, 'credit', 50)->assertCreated();

        $doc = $this->dteNota(CreditNote::firstOrFail())->document();
        $this->assertCumpleEsquema($doc, 'fe-nc-v4.json');
        $this->assertEquals(0.50, $doc['resumen']['ivaRete']);          // 1 % de 50, sin mínimo
        $this->assertEquals(0.50, $doc['cuerpoDocumento'][0]['ivaRete']);
        $this->assertSame('22', $doc['resumen']['codigoRetencionMH']);   // CAT-006: retención IVA 1 %
        $this->assertEquals(56.00, $doc['resumen']['totalPagar']);      // 50 + 6.50 − 0.50
    }

    public function test_un_ccf_con_notas_vigentes_no_se_invalida_hasta_invalidarlas(): void
    {
        $ccf = $this->ccfSellado();
        $this->nota($ccf)->assertCreated();
        $note = CreditNote::firstOrFail();

        $this->invalidar("/api/v1/invoices/{$ccf->id}/invalidate-dte")->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'El CCF tiene notas de crédito o débito vigentes: primero hay que invalidarlas.');

        // La nota de crédito se invalida sin reemplazo, aun con el tipo 1.
        $this->invalidar("/api/v1/credit-notes/{$note->id}/invalidate-dte", ['tipo_anulacion' => 1])->assertCreated();
        $this->assertSame(Invoice::DTE_INVALIDATED, $note->fresh()->dte_status);
        $this->assertSame(DteDocument::INVALIDATED, $this->dteNota($note)->estado);

        $this->invalidar("/api/v1/invoices/{$ccf->id}/invalidate-dte")->assertCreated();
    }

    public function test_la_nota_de_debito_exige_reemplazo_con_el_tipo_uno(): void
    {
        $ccf = $this->ccfSellado();
        $this->nota($ccf, 'debit', 25)->assertCreated();
        $note = CreditNote::firstOrFail();

        $this->invalidar("/api/v1/credit-notes/{$note->id}/invalidate-dte", ['tipo_anulacion' => 1])->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'Con el tipo 1 primero se emite el documento que reemplaza a este y se indica su código de generación.');
    }

    public function test_una_nota_sin_respuesta_se_reintenta(): void
    {
        $ccf = $this->ccfSellado();
        $this->recepciones = [self::SIN_SELLO];

        $this->nota($ccf)->assertStatus(202)->assertJsonPath('data.attributes.dte_status', Invoice::DTE_PENDING);
        $note = CreditNote::firstOrFail();

        $this->apiJson('POST', "/api/v1/credit-notes/{$note->id}/transmit")->assertOk()
            ->assertJsonPath('data.attributes.dte_status', Invoice::DTE_ACCEPTED);
        $this->assertSame(1, $note->dteDocuments()->count()); // el mismo documento
    }

    public function test_con_el_mh_caido_la_nota_sale_en_contingencia(): void
    {
        $ccf = $this->ccfSellado();
        $this->recepciones = [500, 500];

        $this->nota($ccf)->assertStatus(202)->assertJsonPath('data.attributes.dte_status', Invoice::DTE_CONTINGENCY);

        $doc = $this->dteNota(CreditNote::firstOrFail())->document();
        $this->assertCumpleEsquema($doc, 'fe-nc-v4.json');
        $this->assertSame(2, $doc['identificacion']['tipoModelo']);
    }

    public function test_la_nota_se_descarga_y_se_lista_con_su_ccf(): void
    {
        $ccf = $this->ccfSellado();
        $this->nota($ccf)->assertCreated();
        $dte = $this->dteNota(CreditNote::firstOrFail());

        $this->get("/api/v1/dte/documents/{$dte->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("/api/v1/dte/documents/{$dte->id}/json")->assertOk()->assertJsonPath('selloRecibido', self::SELLO);

        $data = app(DteRepresentation::class)->data($dte);
        $this->assertSame('NOTA DE CRÉDITO', $data['titulo']);
        $this->assertSame($this->dte($ccf)->codigo_generacion, $data['relacionados'][0]['numeroDocumento']);
        $this->assertNotContains('Sub-total', array_column($data['totales'], 0));

        $this->apiJson('GET', "/api/v1/invoices/{$ccf->id}/credit-notes")->assertOk()
            ->assertJsonPath('data.0.attributes.number', 'NC-00001')
            ->assertJsonPath('data.0.attributes.dte_documents.0.estado', DteDocument::TRANSMITTED);
    }

    public function test_con_el_dte_desactivado_no_se_guarda_la_nota(): void
    {
        $ccf = $this->ccfSellado();
        $this->configurar(['dte_enabled' => false]);

        $this->nota($ccf)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'La facturación electrónica no está habilitada en la configuración.');
        $this->assertSame(0, CreditNote::count());
    }
}
