<?php

namespace Tests\Feature\Travel;

use App\Models\DteContingencia;
use App\Models\DteDocument;
use App\Models\DteInvalidacion;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

/**
 * Eventos del DTE contra los esquemas oficiales y un MH simulado: invalidación
 * de un DTE sellado y el ciclo completo de una contingencia.
 */
class DteEventosTest extends DteTestCase
{
    private function invalidar(Invoice $invoice, array $attrs = []): TestResponse
    {
        return $this->apiJson('POST', "/api/v1/invoices/{$invoice->id}/invalidate-dte", ['data' => [
            'type' => 'dte-invalidations',
            'attributes' => array_merge([
                'tipo_anulacion' => 2,
                'solicita_nombre' => 'María González',
                'solicita_tipo_doc' => '13',
                'solicita_num_doc' => '01234567-8',
            ], $attrs),
        ]]);
    }

    private function facturaSellada(): Invoice
    {
        $invoice = $this->facturaManual();
        $this->emitir($invoice)->assertOk();

        return $invoice->fresh();
    }

    private function jwsPayload(string $jws): array
    {
        return json_decode(base64_decode(strtr(explode('.', $jws)[1], '-_', '+/')), true);
    }

    // ─── Invalidación ─────────────────────────────────────────────────────────

    public function test_rescindir_una_venta_invalida_el_dte_sin_reemplazo(): void
    {
        $invoice = $this->facturaSellada();
        $doc = $this->dte($invoice);

        $this->invalidar($invoice, ['motivo' => 'El cliente canceló el tour'])
            ->assertCreated()
            ->assertJsonPath('data.attributes.estado', DteInvalidacion::TRANSMITTED)
            ->assertJsonPath('data.attributes.sello_recibido', self::SELLO_EVENTO);

        $inv = DteInvalidacion::firstOrFail();
        $evento = json_decode($inv->json_content, true);
        $this->assertCumpleEsquema($evento, 'invalidacion-schema-v3.json');

        $this->assertSame(3, $evento['identificacion']['version']);
        $this->assertSame('2026-09-24', $evento['identificacion']['fecEmi']); // hora de El Salvador
        $this->assertSame('M001', $evento['emisor']['codEstableMH']);        // sin código del MH, el interno
        $this->assertSame($doc->codigo_generacion, $evento['documento']['codigoGeneracion']);
        $this->assertSame(self::SELLO, $evento['documento']['selloRecibido']);
        $this->assertSame($doc->numero_control, $evento['documento']['numeroControl']);
        $this->assertNull($evento['documento']['codigoGeneracionR']);
        // El receptor tal como lo declaró el DTE.
        $this->assertSame('María González', $evento['documento']['nombre']);
        $this->assertSame('maria@example.com', $evento['documento']['correo']);
        // Quien la realiza es, por defecto, el responsable del establecimiento.
        $this->assertSame('Ana Martínez', $evento['motivo']['nombreResponsable']);
        $this->assertSame('María González', $evento['motivo']['nombreSolicita']);

        $envio = $this->llamadas('/fesv/anulardte')[0];
        $this->assertSame(3, $envio['version']);
        $this->assertSame('00', $envio['ambiente']);
        $this->assertSame($evento, $this->jwsPayload($envio['documento']));

        $this->assertSame(DteDocument::INVALIDATED, $doc->fresh()->estado);
        $this->assertSame(Invoice::DTE_INVALIDATED, $invoice->fresh()->dte_status);
        $this->assertSame('cancelled', $invoice->fresh()->status);

        // Anulada: ni se vuelve a emitir ni se invalida otra vez.
        $this->emitir($invoice)->assertStatus(422);
        $this->invalidar($invoice)->assertStatus(422)->assertJsonPath('errors.0.detail', 'El DTE ya está invalidado.');
    }

    public function test_un_error_en_la_informacion_exige_una_factura_de_reemplazo_sellada(): void
    {
        $original = $this->facturaSellada();

        $this->invalidar($original, ['tipo_anulacion' => 1])->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'Con el tipo 1 primero se emite la factura que reemplaza a esta y se indica su código de generación.');

        // Un reemplazo sin sello no vale.
        $sinSello = $this->facturaManual();
        $this->recepciones = [self::SIN_SELLO];
        $this->emitir($sinSello)->assertStatus(202);
        $this->invalidar($original, ['tipo_anulacion' => 1, 'codigo_generacion_r' => $this->dte($sinSello)->codigo_generacion])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'El DTE de reemplazo debe ser otro documento del mismo tipo, con sello de recepción.');

        // La factura corregida, sellada, sí.
        $corregida = $this->facturaSellada();
        $this->apiJson('GET', "/api/v1/invoices/{$original->id}/dte-replacements")->assertOk()
            ->assertJsonFragment(['codigo_generacion' => $this->dte($corregida)->codigo_generacion]);

        $this->invalidar($original, ['tipo_anulacion' => 1, 'codigo_generacion_r' => strtolower($this->dte($corregida)->codigo_generacion)])
            ->assertCreated()
            ->assertJsonPath('data.attributes.codigo_generacion_r', $this->dte($corregida)->codigo_generacion);

        $evento = json_decode(DteInvalidacion::firstOrFail()->json_content, true);
        $this->assertCumpleEsquema($evento, 'invalidacion-schema-v3.json');
        $this->assertSame($this->dte($corregida)->codigo_generacion, $evento['documento']['codigoGeneracionR']);
    }

    public function test_el_tipo_otro_exige_motivo_y_la_factura_tiene_tres_meses(): void
    {
        $invoice = $this->facturaSellada();

        $this->invalidar($invoice, ['tipo_anulacion' => 3, 'codigo_generacion_r' => str_repeat('A', 36)])->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'Con el tipo 3 (otro) hay que indicar el motivo.');

        $this->travel(4)->months();
        $this->invalidar($invoice)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'Venció el plazo para invalidar la factura (3 meses desde su transmisión).');
        $this->assertCount(0, $this->llamadas('/fesv/anulardte'));
    }

    public function test_solo_se_invalida_un_dte_con_sello(): void
    {
        $invoice = $this->facturaManual();
        $this->invalidar($invoice)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'Solo se invalida un DTE con sello de recepción.');

        // Tampoco uno emitido en contingencia: todavía no tiene sello.
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);
        $this->invalidar($invoice)->assertStatus(422);
    }

    public function test_una_invalidacion_sin_respuesta_se_reenvia_la_misma(): void
    {
        $invoice = $this->facturaSellada();
        $this->anulaciones = [500];

        $this->invalidar($invoice)->assertStatus(202)
            ->assertJsonPath('data.attributes.estado', DteInvalidacion::PENDING);
        $this->assertSame(Invoice::DTE_ACCEPTED, $invoice->fresh()->dte_status); // sigue valiendo

        $this->travel(3)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $this->assertSame(1, DteInvalidacion::count());
        $inv = DteInvalidacion::firstOrFail();
        $this->assertSame(DteInvalidacion::TRANSMITTED, $inv->estado);
        $this->assertSame(2, $inv->intentos);
        $this->assertSame($inv->firma_electronica, $this->llamadas('/fesv/anulardte')[1]['documento']);
        $this->assertSame(Invoice::DTE_INVALIDATED, $invoice->fresh()->dte_status);
    }

    public function test_un_rechazo_de_la_invalidacion_deja_el_dte_vigente(): void
    {
        $invoice = $this->facturaSellada();
        $this->anulaciones = [['estado' => 'RECHAZADO']];

        $this->invalidar($invoice)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'El MH rechazó la invalidación: 004 [identificacion.numeroControl] YA EXISTE');

        $this->assertSame(DteInvalidacion::REJECTED, DteInvalidacion::firstOrFail()->estado);
        $this->assertSame(Invoice::DTE_ACCEPTED, $invoice->fresh()->dte_status);

        // Se puede presentar otra, corregida.
        $this->invalidar($invoice)->assertCreated();
        $this->assertSame(2, DteInvalidacion::count());
    }

    public function test_invalidar_requiere_su_propio_permiso(): void
    {
        $invoice = $this->facturaSellada();

        $editor = User::create([
            'username' => 'editor'.uniqid(), 'first_name' => 'E', 'last_name' => 'D',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $editor->assignRole('editor');
        Passport::actingAs($editor->fresh());

        $this->invalidar($invoice)->assertForbidden();
    }

    // ─── Contingencia ─────────────────────────────────────────────────────────

    public function test_con_el_mh_caido_el_dte_pasa_a_contingencia(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];

        $this->emitir($invoice)->assertStatus(202)
            ->assertJsonPath('data.attributes.dte_status', Invoice::DTE_CONTINGENCY);

        $doc = $this->dte($invoice);
        $json = $doc->document();
        $this->assertSame(DteDocument::CONTINGENCY, $doc->estado);
        $this->assertCumpleEsquema($json);
        $this->assertSame(2, $json['identificacion']['tipoModelo']);
        $this->assertSame(2, $json['identificacion']['tipoOperacion']);
        $this->assertSame(1, $json['identificacion']['tipoContingencia']);
        $this->assertNull($json['identificacion']['motivoContin']);

        // Mismo código y número; firmado de nuevo sobre el JSON nuevo.
        $enLinea = json_decode($doc->json_en_linea, true);
        $this->assertSame($enLinea['identificacion']['codigoGeneracion'], $json['identificacion']['codigoGeneracion']);
        $this->assertSame($enLinea['identificacion']['numeroControl'], $json['identificacion']['numeroControl']);
        $this->assertSame(1, $enLinea['identificacion']['tipoModelo']);
        $this->assertSame($doc->json_content, json_encode($this->jwsPayload($doc->firma_electronica), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // Los importes no cambian al reescribir.
        $this->assertSame($enLinea['resumen'], $json['resumen']);

        $c = DteContingencia::firstOrFail();
        $this->assertSame(DteContingencia::OPEN, $c->estado);
        $this->assertSame($c->id, $doc->contingencia_id);
    }

    public function test_con_una_contingencia_abierta_se_emite_sin_esperar_al_mh(): void
    {
        $this->recepciones = [500, 500];
        $this->emitir($this->facturaManual())->assertStatus(202);

        $segunda = $this->facturaManual();
        $this->emitir($segunda)->assertStatus(202);

        $this->assertCount(2, $this->llamadas('/fesv/recepciondte')); // solo los de la primera
        $doc = $this->dte($segunda);
        $this->assertSame(DteDocument::CONTINGENCY, $doc->estado);
        $this->assertNull($doc->json_en_linea);
        $this->assertStringEndsWith('000000000000002', $doc->numero_control);
        $this->assertSame(2, $doc->document()['identificacion']['tipoModelo']);
    }

    public function test_mientras_el_mh_siga_caido_la_contingencia_sigue_abierta(): void
    {
        $this->recepciones = [500, 500];
        $this->emitir($this->facturaManual())->assertStatus(202);

        $this->consultas = [503];
        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $c = DteContingencia::firstOrFail();
        $this->assertSame(DteContingencia::OPEN, $c->estado);
        $this->assertNull($c->fin);
        $this->assertNotNull($c->ultimo_error);
    }

    public function test_cuando_el_mh_vuelve_se_envia_el_evento_y_el_lote(): void
    {
        $this->recepciones = [500, 500];
        $a = $this->facturaManual();
        $b = $this->facturaManual();
        $this->emitir($a)->assertStatus(202);
        $this->emitir($b)->assertStatus(202);
        $docA = $this->dte($a);
        $docB = $this->dte($b);

        $this->consultasLote = [['procesados' => [
            ['codigoGeneracion' => $docA->codigo_generacion, 'estado' => 'PROCESADO', 'selloRecibido' => self::SELLO],
            ['codigoGeneracion' => $docB->codigo_generacion, 'estado' => 'PROCESADO', 'selloRecibido' => strrev(self::SELLO)],
        ], 'rechazados' => []]];

        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $c = DteContingencia::firstOrFail();
        $this->assertSame(DteContingencia::DONE, $c->estado);
        $this->assertNotNull($c->fin);
        $this->assertSame(self::SELLO_EVENTO, $c->sello_recibido);
        $this->assertSame('LOTE-0001', $c->codigo_lote);

        // Evento según el esquema v4, con el responsable y los dos DTE.
        $evento = json_decode($c->json_content, true);
        $this->assertCumpleEsquema($evento, 'contingencia-schema-v4.json');
        $this->assertSame('Ana Martínez', $evento['emisor']['nombreResponsable']);
        $this->assertSame(
            [$docA->codigo_generacion, $docB->codigo_generacion],
            array_column($evento['detalleDTE'], 'codigoGeneracion'),
        );
        $this->assertSame(1, $evento['motivo']['tipoContingencia']);
        $this->assertSame('06140101231234', $this->llamadas('/fesv/contingencia')[0]['nit']);

        // El lote lleva los documentos firmados tal como se generaron.
        $this->assertSame([$docA->firma_electronica, $docB->firma_electronica], $this->llamadas('/fesv/recepcionlote/')[0]['documentos']);

        $this->assertSame(DteDocument::TRANSMITTED, $docA->fresh()->estado);
        $this->assertSame(strrev(self::SELLO), $docB->fresh()->sello_recibido);
        $this->assertSame(Invoice::DTE_ACCEPTED, $a->fresh()->dte_status);
        $this->assertSame(Invoice::DTE_ACCEPTED, $b->fresh()->dte_status);
    }

    public function test_un_documento_que_si_llego_recupera_su_version_en_linea(): void
    {
        $this->recepciones = [500, 500];
        $a = $this->facturaManual();
        $b = $this->facturaManual();
        $this->emitir($a)->assertStatus(202);
        $this->emitir($b)->assertStatus(202);
        $docA = $this->dte($a);

        // Sondeo (el MH contesta) y conciliación de A: el MH sí lo tenía.
        $this->consultas = [404, ['estado' => 'PROCESADO', 'sello' => self::SELLO]];
        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $docA->refresh();
        $this->assertSame(DteDocument::TRANSMITTED, $docA->estado);
        $this->assertNull($docA->contingencia_id);
        $this->assertSame($docA->json_en_linea, $docA->json_content);
        $this->assertSame(1, $docA->document()['identificacion']['tipoModelo']);

        // El evento solo reporta el que de verdad quedó en contingencia.
        $evento = json_decode(DteContingencia::firstOrFail()->json_content, true);
        $this->assertSame([$this->dte($b)->codigo_generacion], array_column($evento['detalleDTE'], 'codigoGeneracion'));
    }

    public function test_un_evento_rechazado_se_rearma_con_codigo_nuevo(): void
    {
        $this->recepciones = [500, 500];
        $this->emitir($this->facturaManual())->assertStatus(202);

        $this->eventosContingencia = [['estado' => 'RECHAZADO', 'mensaje' => 'RESPONSABLE NO VALIDO', 'observaciones' => []]];
        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $c = DteContingencia::firstOrFail();
        $this->assertSame(DteContingencia::EVENT_REJECTED, $c->estado);
        $this->assertSame('RESPONSABLE NO VALIDO', $c->ultimo_error);
        $primerCodigo = $c->codigo_generacion;

        // El comando no lo toca solo: hay que corregir y rearmar.
        $this->artisan('dte:retry')->assertSuccessful();
        $this->assertCount(1, $this->llamadas('/fesv/contingencia'));

        $this->admin();
        $this->apiJson('POST', "/api/v1/dte/contingencias/{$c->id}/process")->assertOk()
            ->assertJsonPath('data.attributes.estado', DteContingencia::LOTE_SENT);

        $this->assertNotSame($primerCodigo, $c->fresh()->codigo_generacion);
    }

    public function test_un_documento_rechazado_en_el_lote_se_puede_volver_a_emitir(): void
    {
        $this->recepciones = [500, 500];
        $invoice = $this->facturaManual();
        $this->emitir($invoice)->assertStatus(202);
        $doc = $this->dte($invoice);

        $this->consultasLote = [['procesados' => [], 'rechazados' => [[
            'codigoGeneracion' => $doc->codigo_generacion, 'estado' => 'RECHAZADO',
            'codigoMsg' => '096', 'descripcionMsg' => 'DOCUMENTO NO REPORTADO EN EVENTO', 'observaciones' => [],
        ]]]];
        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $this->assertSame(DteDocument::REJECTED, $doc->fresh()->estado);
        $this->assertSame(Invoice::DTE_REJECTED, $invoice->fresh()->dte_status);
        $this->assertSame(DteContingencia::DONE, DteContingencia::firstOrFail()->estado);

        // Con el MH de vuelta, uno nuevo sale en línea.
        $this->emitir($invoice)->assertOk();
        $this->assertSame(1, $this->dte($invoice)->document()['identificacion']['tipoModelo']);
    }

    public function test_el_listado_de_contingencias_muestra_el_plazo_que_corre(): void
    {
        $this->recepciones = [500, 500];
        $this->emitir($this->facturaManual())->assertStatus(202);

        // Cerrada (el MH volvió) pero con el evento sin enviar por un emisor incompleto.
        $this->configurar(['dte_responsable_nombre' => '']);
        $this->travel(10)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();
        $c = DteContingencia::firstOrFail();
        $this->assertSame(DteContingencia::CLOSED, $c->estado);
        $this->assertStringContainsString('responsable', $c->ultimo_error);

        $this->admin();
        $fila = $this->apiJson('GET', '/api/v1/dte/contingencias')->assertOk()->json('data.0.attributes');
        $this->assertSame(1, $fila['pendientes']);
        $this->assertFalse($fila['vencida']);
        $this->assertEquals($c->fin->copy()->addHours(24), Carbon::parse($fila['proximo_vencimiento']));

        $this->travel(25)->hours();
        $this->assertTrue($this->apiJson('GET', '/api/v1/dte/contingencias')->json('data.0.attributes.vencida'));
    }
}
