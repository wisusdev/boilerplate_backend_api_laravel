<?php

namespace Tests\Feature\Travel;

use App\Jobs\DeliverDteDocument;
use App\Mail\DteDocumentMail;
use App\Models\DteDocument;
use App\Models\Invoice;
use App\Services\Dte\DteDelivery;
use App\Services\Dte\DteRepresentation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

/**
 * Entrega del DTE al receptor: representación gráfica (PDF), archivo DTE
 * (JSON firmado con su sello) y envío por correo.
 */
class DteEntregaTest extends DteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function sellada(array $receptor = []): Invoice
    {
        $invoice = $this->facturaManual(receptor: $receptor);
        $this->emitir($invoice)->assertOk();

        return $invoice->fresh();
    }

    private function enviar(Invoice $invoice, ?string $to = null)
    {
        return $this->apiJson('POST', "/api/v1/invoices/{$invoice->id}/dte/send",
            $to ? ['data' => ['type' => 'dte-deliveries', 'attributes' => ['to' => $to]]] : []);
    }

    private function html(DteDocument $doc): string
    {
        return view('pdf.dte.documento', ['d' => app(DteRepresentation::class)->data($doc)])->render();
    }

    // ─── Representación gráfica y archivo ─────────────────────────────────────

    public function test_la_representacion_grafica_sale_del_json_sellado(): void
    {
        $invoice = $this->sellada();
        $doc = $this->dte($invoice);

        $pdf = $this->get("/api/v1/invoices/{$invoice->id}/dte/pdf")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$doc->codigo_generacion.'.pdf"');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        // El QR está embebido de verdad (dompdf descarta en silencio una imagen que no puede cargar).
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());

        $html = $this->html($doc);
        $this->assertStringContainsString('DOCUMENTO TRIBUTARIO ELECTRÓNICO', $html);
        $this->assertStringContainsString('AMBIENTE DE PRUEBAS — SIN VALIDEZ TRIBUTARIA', $html);
        $this->assertStringContainsString(self::SELLO, $html);
        $this->assertStringContainsString($doc->numero_control, $html);
        $this->assertStringContainsString('Modelo de facturación previo', $html);
        $this->assertStringContainsString('CIENTO CUARENTA Y UN DÓLARES CON 30/100', $html);
        $this->assertStringContainsString('$141.30', $html);
        // Direcciones con los nombres del catálogo, no con códigos.
        $this->assertStringContainsString('Calle La Mascota #123, Col. Escalón, San Salvador, San Salvador Centro', $html);
        // Secciones "D" sin usar: su nombre y un guion.
        $this->assertStringContainsString('VENTA POR CUENTA DE TERCEROS', $html);

        // Con sello, un QR a la consulta pública del MH.
        $data = app(DteRepresentation::class)->data($doc);
        $this->assertStringStartsWith('data:image/png;base64,', $data['qr']);
        $this->assertSame(
            'https://admin.factura.gob.sv/consultaPublica?ambiente=00&codGen='.$doc->codigo_generacion.'&fechaEmi=2026-09-24',
            DteRepresentation::consultaUrl('00', $doc->codigo_generacion, '2026-09-24'),
        );
    }

    public function test_en_contingencia_el_pdf_dice_que_el_sello_esta_pendiente(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);
        $doc = $this->dte($invoice);

        $data = app(DteRepresentation::class)->data($doc);
        $this->assertNull($data['qr']);
        $this->assertStringStartsWith('Pendiente: emitido en contingencia', $data['identificacion']['Sello de recepción']);
        $this->assertSame('Modelo de facturación diferido', $data['identificacion']['Modelo de facturación']);
        $this->assertSame('Transmisión por contingencia', $data['identificacion']['Tipo de transmisión']);
        $this->assertSame('1 — No disponibilidad de sistema del MH', $data['identificacion']['Tipo de contingencia']);
    }

    public function test_el_archivo_dte_lleva_la_firma_y_el_sello(): void
    {
        $invoice = $this->sellada();
        $doc = $this->dte($invoice);

        $json = $this->get("/api/v1/invoices/{$invoice->id}/dte/json")->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$doc->codigo_generacion.'.json"')
            ->json();

        $this->assertSame($doc->firma_electronica, $json['firmaElectronica']);
        $this->assertSame(self::SELLO, $json['selloRecibido']);
        unset($json['firmaElectronica'], $json['selloRecibido']);
        $this->assertSame($doc->document(), $json);
    }

    public function test_sin_dte_no_hay_nada_que_descargar(): void
    {
        $invoice = $this->facturaManual();

        $this->get("/api/v1/invoices/{$invoice->id}/dte/pdf")->assertNotFound();
        $this->enviar($invoice)->assertNotFound();
    }

    // ─── Envío manual ─────────────────────────────────────────────────────────

    public function test_se_envia_al_correo_que_declara_el_dte(): void
    {
        $invoice = $this->sellada();
        $doc = $this->dte($invoice);

        $this->enviar($invoice)->assertOk()
            ->assertJsonPath('data.attributes.entregado_a', 'maria@example.com')
            ->assertJsonPath('data.attributes.entregado_con_sello', true);

        Mail::assertSent(DteDocumentMail::class, function (DteDocumentMail $mail) use ($doc) {
            $adjuntos = collect($mail->attachments())->map(fn ($a) => $a->as)->all();

            return $mail->hasTo('maria@example.com')
                && $adjuntos === [$doc->codigo_generacion.'.pdf', $doc->codigo_generacion.'.json']
                && $mail->envelope()->subject === "Factura electrónica {$doc->numero_control} — VAMOS PUES, S.A. DE C.V.";
        });

        $doc->refresh();
        $this->assertNotNull($doc->entregado_at);
        $this->assertTrue($doc->entregado_con_sello);
    }

    public function test_sin_correo_del_receptor_hay_que_indicar_uno(): void
    {
        $invoice = $this->sellada(['receptor_email' => null]);

        $this->enviar($invoice)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'El DTE no tiene correo del receptor: indica a qué correo enviarlo.');
        Mail::assertNothingSent();

        $this->enviar($invoice, 'contabilidad@cliente.test')->assertOk();
        Mail::assertSent(DteDocumentMail::class, fn ($m) => $m->hasTo('contabilidad@cliente.test'));
    }

    public function test_el_correo_explica_si_va_sin_sello(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);

        $html = (new DteDocumentMail($this->dte($invoice)))->render();
        $this->assertStringContainsString('emitido en contingencia', $html);
        $this->assertStringContainsString('te enviaremos la versión con sello', $html);
    }

    // ─── Entrega automática ───────────────────────────────────────────────────

    public function test_en_pruebas_no_se_envia_nada_solo(): void
    {
        Bus::fake([DeliverDteDocument::class]);

        $this->sellada();

        Bus::assertNotDispatched(DeliverDteDocument::class);
    }

    public function test_en_produccion_se_entrega_al_sellarse(): void
    {
        $this->configurar(['dte_environment' => 'production']);
        Bus::fake([DeliverDteDocument::class]);

        $invoice = $this->sellada();
        $doc = $this->dte($invoice);

        Bus::assertDispatchedAfterResponse(DeliverDteDocument::class, fn ($job) => $job->documentId === $doc->id);

        (new DeliverDteDocument($doc->id))->handle(app(DteDelivery::class));
        Mail::assertSent(DteDocumentMail::class, 1);
        $this->assertTrue($doc->fresh()->entregado_con_sello);

        // Ya entregado con sello: el job no lo repite.
        (new DeliverDteDocument($doc->id))->handle(app(DteDelivery::class));
        Mail::assertSent(DteDocumentMail::class, 1);
    }

    public function test_en_contingencia_se_entrega_sin_sello_y_despues_con_sello(): void
    {
        $this->configurar(['dte_environment' => 'production']);
        Bus::fake([DeliverDteDocument::class]);

        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);
        $doc = $this->dte($invoice);

        // Al generarse en contingencia: la entrega sin sello.
        Bus::assertDispatchedAfterResponse(DeliverDteDocument::class, 1);
        (new DeliverDteDocument($doc->id))->handle(app(DteDelivery::class));
        $doc->refresh();
        $this->assertNotNull($doc->entregado_at);
        $this->assertFalse($doc->entregado_con_sello);

        // El MH vuelve y el lote lo sella: la versión definitiva.
        $this->consultasLote = [['procesados' => [
            ['codigoGeneracion' => $doc->codigo_generacion, 'estado' => 'PROCESADO', 'selloRecibido' => self::SELLO],
        ], 'rechazados' => []]];
        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        Bus::assertDispatchedAfterResponse(DeliverDteDocument::class, 2);
        (new DeliverDteDocument($doc->id))->handle(app(DteDelivery::class));
        Mail::assertSent(DteDocumentMail::class, 2);
        $this->assertTrue($doc->fresh()->entregado_con_sello);
    }

    public function test_sin_correo_del_receptor_no_se_intenta_la_entrega_automatica(): void
    {
        $this->configurar(['dte_environment' => 'production']);
        Bus::fake([DeliverDteDocument::class]);

        $this->sellada(['receptor_email' => null]);

        Bus::assertNotDispatched(DeliverDteDocument::class);
    }
}
