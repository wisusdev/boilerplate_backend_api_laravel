<?php

namespace Tests\Feature\Travel;

use App\Jobs\TransmitInvoiceDte;
use App\Models\Booking;
use App\Models\DteDocument;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Services\Dte\AmountToWords;
use App\Services\Dte\DteSigner;
use App\Services\DteService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

/**
 * Factura de consumidor final (DTE 01, esquema v2) contra el esquema oficial
 * del MH y un MH simulado. Sin credenciales de Hacienda, esto verifica casi
 * todo antes de tocar apitest.
 */
class DteFacturaTest extends TestCase
{
    use RefreshDatabase;

    private const SELLO = '2026A1B2C3D4E5F6A7B8C9D0E1F2A3B4C5D6E7F8';

    private \OpenSSLAsymmetricKey $key;

    /** @var list<array{estado: string, sello?: string}|int> */
    private array $recepciones = [];

    /** @var list<array<string, mixed>|int> */
    private array $consultas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Storage::fake('local');
        DteService::$retryDelayMs = 0;
        Carbon::setTestNow('2026-09-25 03:30:00'); // UTC: en El Salvador aún es el 24 a las 21:30

        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->configurar();
        $this->fakeMh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Escenario ────────────────────────────────────────────────────────────

    private function configurar(array $extra = []): void
    {
        Setting::updateOrCreate(['key' => 'dte'], ['value' => json_encode(array_merge([
            'dte_enabled' => true,
            'dte_environment' => 'test',
            'dte_nit' => '0614-010123-123-4',
            'dte_nrc' => '123456-7',
            'dte_nombre' => 'VAMOS PUES, S.A. DE C.V.',
            'dte_nombre_comercial' => 'Vamos Pues',
            'dte_cod_actividad' => '79110',
            'dte_departamento' => '06',
            'dte_municipio' => '23',
            'dte_distrito' => '14',
            'dte_direccion' => 'Calle La Mascota #123, Col. Escalón',
            'dte_telefono' => '22223333',
            'dte_correo' => 'facturacion@vamospues.test',
            'dte_cod_establec' => 'M001',
            'dte_cod_punto_venta' => 'P001',
            'dte_mh_password' => Crypt::encryptString('clave-api-mh'),
        ], $extra))]);

        $this->cargarCertificado();
    }

    private function cargarCertificado(): void
    {
        $csr = openssl_csr_new(['commonName' => 'VAMOS PUES'], $this->key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $this->key, 365, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($cert, $p12, $this->key, 'clave-cert');

        $tmp = tempnam(sys_get_temp_dir(), 'p12');
        file_put_contents($tmp, $p12);
        app(DteService::class)->uploadCertificate($tmp, 'clave-cert');
        unlink($tmp);
    }

    /**
     * MH simulado: responde con lo que haya en $recepciones / $consultas, en
     * orden (un entero es un código HTTP sin cuerpo útil).
     */
    private function fakeMh(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/seguridad/auth')) {
                return Http::response(['status' => 'OK', 'body' => ['token' => 'Bearer token-de-prueba']]);
            }
            if (str_contains($request->url(), '/fesv/recepcion/consultadte')) {
                return $this->respuesta(array_shift($this->consultas) ?? 404);
            }
            if (str_ends_with($request->url(), '/fesv/recepciondte')) {
                return $this->respuesta(array_shift($this->recepciones) ?? ['estado' => 'PROCESADO', 'sello' => self::SELLO]);
            }

            return Http::response('no encontrado', 404);
        });
    }

    private function respuesta(array|int $r)
    {
        if (is_int($r)) {
            return Http::response('<html>error</html>', $r);
        }

        return Http::response([
            'version' => 2, 'ambiente' => '00', 'estado' => $r['estado'],
            'selloRecibido' => $r['sello'] ?? null, 'fhProcesamiento' => '24/09/2026 21:30:01',
            'codigoMsg' => $r['estado'] === 'RECHAZADO' ? '004' : '001',
            'descripcionMsg' => $r['estado'] === 'RECHAZADO' ? '[identificacion.numeroControl] YA EXISTE' : 'RECIBIDO',
            'observaciones' => [],
        ]);
    }

    private function admin(): User
    {
        $user = User::create([
            'username' => 'dte'.uniqid(), 'first_name' => 'Admin', 'last_name' => 'DTE',
            'email' => uniqid('dte').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');
        Passport::actingAs($user->fresh());

        return $user;
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function facturaManual(?array $items = null, array $receptor = []): Invoice
    {
        $this->admin();
        $items ??= [
            ['description' => 'Hamburguesa de tour', 'quantity' => 1, 'unit_price' => 11.30],
            ['description' => 'Tour Volcán Santa Ana', 'quantity' => 2, 'unit_price' => 65],
        ];

        $id = $this->apiJson('POST', '/api/v1/invoices', ['data' => ['type' => 'invoices', 'attributes' => array_merge([
            'receptor_name' => 'María González',
            'receptor_email' => 'maria@example.com',
            'items' => $items,
        ], $receptor)]])->assertCreated()->json('data.id');

        return Invoice::findOrFail($id);
    }

    private function emitir(Invoice $invoice): TestResponse
    {
        return $this->apiJson('POST', "/api/v1/invoices/{$invoice->id}/generate-dte");
    }

    /** El DTE vigente (el último) de una factura. */
    private function dte(Invoice $invoice): DteDocument
    {
        return $invoice->dteDocuments()->latest('id')->firstOrFail();
    }

    private function documento(Invoice $invoice): array
    {
        return $this->dte($invoice)->document();
    }

    private function assertCumpleEsquema(array $documento): void
    {
        // El MH lee el texto JSON ("141.3") como decimal. Con su escala por
        // defecto (14) opis compara el binario 141.30000000000001 y falla el
        // multipleOf 0.01; 8 decimales es el multipleOf más fino del esquema.
        Helper::$numberScale = 8;
        $validator = new Validator;
        $validator->setMaxErrors(20);
        $result = $validator->validate(
            json_decode(json_encode($documento)),
            file_get_contents(base_path('tests/Fixtures/dte-schemas/fe-f-v2.json')),
        );

        $this->assertTrue(
            $result->isValid(),
            $result->isValid() ? '' : json_encode((new ErrorFormatter)->format($result->error()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /** @return list<Request> */
    private function llamadas(string $ruta): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), $ruta))->map(fn ($par) => $par[0])->values()->all();
    }

    // ─── Documento ────────────────────────────────────────────────────────────

    public function test_la_factura_cumple_el_esquema_oficial_con_iva_incluido_por_linea(): void
    {
        $invoice = $this->facturaManual();

        $this->emitir($invoice)->assertOk()->assertJsonPath('data.attributes.dte_status', Invoice::DTE_ACCEPTED);

        $doc = $this->documento($invoice);
        $this->assertCumpleEsquema($doc);

        // Factura: precios CON IVA, IVA por línea y sin tributo 20.
        $this->assertSame('01', $doc['identificacion']['tipoDte']);
        $this->assertSame(2, $doc['identificacion']['version']);
        $this->assertEquals(11.30, $doc['cuerpoDocumento'][0]['ventaGravada']);
        $this->assertEquals(1.3, $doc['cuerpoDocumento'][0]['ivaItem']);
        $this->assertEquals(130, $doc['cuerpoDocumento'][1]['ventaGravada']);
        $this->assertEquals(65, $doc['cuerpoDocumento'][1]['precioUni']);
        $this->assertEquals(14.95575221, $doc['cuerpoDocumento'][1]['ivaItem']);
        $this->assertNull($doc['cuerpoDocumento'][0]['tributos']);
        $this->assertNull($doc['resumen']['tributos']);

        $this->assertEquals(141.30, $doc['resumen']['totalGravada']);
        $this->assertEquals(141.30, $doc['resumen']['montoTotalOperacion']);
        $this->assertEquals(141.30, $doc['resumen']['totalPagar']);
        $this->assertEquals(16.26, $doc['resumen']['totalIva']);
        $this->assertSame('CIENTO CUARENTA Y UN DÓLARES CON 30/100', $doc['resumen']['totalLetras']);

        // Emisor en códigos de catálogo, con distrito y la descripción de CAT-019.
        $this->assertSame('06140101231234', $doc['emisor']['nit']);
        $this->assertSame('1234567', $doc['emisor']['nrc']);
        $this->assertSame(['departamento' => '06', 'municipio' => '23', 'distrito' => '14',
            'complemento' => 'Calle La Mascota #123, Col. Escalón'], $doc['emisor']['direccion']);
        $this->assertStringStartsWith('Actividades de agencias de viajes', $doc['emisor']['descActividad']);
        $this->assertSame('M001', $doc['emisor']['codEstable']);
        $this->assertSame('P001', $doc['emisor']['codPuntoVenta']);

        // Hora de El Salvador, no la del servidor.
        $this->assertSame('2026-09-24', $doc['identificacion']['fecEmi']);
        $this->assertSame('21:30:00', $doc['identificacion']['horEmi']);

        $this->assertSame('INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT), $doc['apendice'][0]['valor']);
    }

    public function test_consumidor_final_anonimo_va_en_null(): void
    {
        $invoice = $this->facturaManual(receptor: ['receptor_name' => 'Consumidor Final', 'receptor_email' => null]);

        $this->emitir($invoice)->assertOk();

        $receptor = $this->documento($invoice)['receptor'];
        $this->assertSame([
            'tipoDocumento' => null, 'numDocumento' => null, 'nrc' => null, 'nombre' => null,
            'codActividad' => null, 'descActividad' => null, 'direccion' => null,
            'telefono' => null, 'correo' => null,
        ], $receptor);
        $this->assertCumpleEsquema($this->documento($invoice));
    }

    public function test_el_documento_del_receptor_se_declara_con_su_tipo(): void
    {
        // NIT con guiones: sin tipo explícito se deduce por los 14 dígitos.
        $nit = $this->facturaManual(receptor: ['receptor_document' => '0614-010190-101-2']);
        $this->emitir($nit)->assertOk();
        $this->assertSame('36', $this->documento($nit)['receptor']['tipoDocumento']);
        $this->assertSame('06140101901012', $this->documento($nit)['receptor']['numDocumento']);

        // DUI: 8 dígitos, guion y verificador.
        $dui = $this->facturaManual(receptor: ['receptor_document' => '012345678', 'receptor_document_type' => '13']);
        $this->emitir($dui)->assertOk();
        $this->assertSame('13', $this->documento($dui)['receptor']['tipoDocumento']);
        $this->assertSame('01234567-8', $this->documento($dui)['receptor']['numDocumento']);

        // Pasaporte: tal cual.
        $pasaporte = $this->facturaManual(receptor: ['receptor_document' => 'A1234567', 'receptor_document_type' => '03']);
        $this->emitir($pasaporte)->assertOk();
        $this->assertSame('03', $this->documento($pasaporte)['receptor']['tipoDocumento']);
        $this->assertSame('A1234567', $this->documento($pasaporte)['receptor']['numDocumento']);
    }

    public function test_una_factura_de_reserva_declara_la_reserva_y_sus_cobros_reales(): void
    {
        $admin = $this->admin();
        $tour = Tour::create([
            'title' => 'Volcán Santa Ana', 'description' => 'd', 'price' => 65,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ]);
        $booking = Booking::create([
            'user_id' => $admin->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(5), 'party_size' => 2, 'total_price' => 130,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);
        $booking->payments()->create(['gateway' => 'manual', 'method' => 'efectivo', 'amount' => 30, 'currency_code' => 'USD', 'status' => 'paid']);
        $booking->payments()->create(['gateway' => 'wompi', 'method' => 'card', 'amount' => 100, 'currency_code' => 'USD', 'status' => 'paid']);
        $booking->payments()->create(['gateway' => 'manual', 'method' => 'efectivo', 'amount' => 50, 'currency_code' => 'USD', 'status' => 'failed']);
        $invoice = Invoice::create([
            'booking_id' => $booking->id, 'amount' => 130, 'currency_code' => 'USD',
            'status' => 'pending', 'dte_status' => Invoice::DTE_NOT_GENERATED, 'issued_at' => now(),
        ]);

        $this->emitir($invoice)->assertOk();

        $doc = $this->documento($invoice);
        $this->assertCumpleEsquema($doc);
        $this->assertCount(1, $doc['cuerpoDocumento']);
        $this->assertSame('Tour — Volcán Santa Ana (2 personas)', $doc['cuerpoDocumento'][0]['descripcion']);
        $this->assertEquals([
            ['codigo' => '01', 'montoPago' => 30, 'referencia' => null, 'plazo' => null, 'periodo' => null],
            ['codigo' => '99', 'montoPago' => 100, 'referencia' => 'Pago en línea', 'plazo' => null, 'periodo' => null],
        ], $doc['resumen']['pagos']);
    }

    // ─── Firma y transmisión ──────────────────────────────────────────────────

    public function test_se_firma_como_jws_rs512_y_se_envia_al_mh(): void
    {
        $invoice = $this->facturaManual();

        $this->emitir($invoice)->assertOk()->assertJsonPath('data.attributes.dte_seal', self::SELLO);
        $invoice->refresh();
        $dte = $this->dte($invoice);

        [$header, $payload, $firma] = explode('.', $dte->firma_electronica);
        $this->assertSame('{"alg":"RS512"}', base64_decode(strtr($header, '-_', '+/')));
        $this->assertStringNotContainsString('=', $dte->firma_electronica);
        $this->assertSame(1, openssl_verify(
            "{$header}.{$payload}",
            base64_decode(strtr($firma, '-_', '+/')),
            openssl_pkey_get_details($this->key)['key'],
            OPENSSL_ALGO_SHA512,
        ));
        // El payload es, byte a byte, el JSON guardado.
        $this->assertSame($dte->json_content, base64_decode(strtr($payload, '-_', '+/')));

        // Autenticación por formulario con el NIT y la contraseña descifrada.
        $auth = $this->llamadas('/seguridad/auth')[0];
        $this->assertSame(['user' => '06140101231234', 'pwd' => 'clave-api-mh'], $auth->data());
        $this->assertStringContainsString('application/x-www-form-urlencoded', $auth->header('Content-Type')[0]);

        $envio = $this->llamadas('/fesv/recepciondte')[0];
        $this->assertSame('Bearer token-de-prueba', $envio->header('Authorization')[0]);
        $this->assertSame('00', $envio['ambiente']);
        $this->assertSame('01', $envio['tipoDte']);
        $this->assertSame(2, $envio['version']);
        $this->assertSame($dte->firma_electronica, $envio['documento']);
        $this->assertSame($invoice->dte_generation_code, $envio['codigoGeneracion']);

        // La contraseña del certificado nunca sale hacia el MH.
        $this->assertStringNotContainsString('clave-cert', json_encode($envio->data()));

        $this->assertSame(Invoice::DTE_ACCEPTED, $invoice->dte_status);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(DteDocument::TRANSMITTED, $dte->estado);
        $this->assertSame(self::SELLO, $dte->sello_recibido);
        $this->assertSame(1, $dte->intentos);
    }

    public function test_el_certificado_del_mh_en_xml_tambien_firma(): void
    {
        openssl_pkey_export($this->key, $pem);
        $pkcs8 = preg_replace('/-----[A-Z ]+-----|\s/', '', $pem);
        $xml = '<CertificadoMH><privateKey><keyType>PRIVATE</keyType><encodied>'.$pkcs8
            .'</encodied><clave>'.hash('sha512', 'clave-mh').'</clave></privateKey></CertificadoMH>';

        $signer = new DteSigner;
        $jws = $signer->sign('{"a":1}', $signer->parseKey($xml, 'clave-mh'));
        [$h, $p, $s] = explode('.', $jws);
        $this->assertSame(1, openssl_verify("{$h}.{$p}", base64_decode(strtr($s, '-_', '+/')),
            openssl_pkey_get_details($this->key)['key'], OPENSSL_ALGO_SHA512));

        $this->expectExceptionMessage('no coincide');
        $signer->parseKey($xml, 'otra');
    }

    public function test_el_certificado_se_guarda_cifrado_con_su_contrasena(): void
    {
        $this->assertStringNotContainsString('clave-cert', Setting::where('key', 'dte')->value('value'));
        $guardado = Storage::disk('local')->get(DteSigner::CERT_PATH);
        $this->assertNotFalse(Crypt::decryptString($guardado));

        $this->admin();
        $this->call('POST', '/api/v1/settings/dte-certificate', ['password' => 'mala'], [], [
            'certificate' => UploadedFile::fake()->createWithContent('cert.p12', 'no es un certificado'),
        ], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(422);
    }

    public function test_el_numero_de_control_es_un_correlativo_por_tipo(): void
    {
        $a = $this->facturaManual();
        $b = $this->facturaManual();

        $this->emitir($a)->assertOk();
        $this->emitir($b)->assertOk();

        $this->assertSame('DTE-01-M001P001-000000000000001', $a->fresh()->dte_number);
        $this->assertSame('DTE-01-M001P001-000000000000002', $b->fresh()->dte_number);
        $this->assertSame(31, strlen($a->fresh()->dte_number));

        // Una segunda llamada sobre una factura aceptada no reenvía nada.
        $this->emitir($a)->assertStatus(422);
        $this->assertCount(2, $this->llamadas('/fesv/recepciondte'));
    }

    public function test_un_rechazo_se_guarda_y_el_reintento_emite_un_documento_nuevo(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [['estado' => 'RECHAZADO']];

        $this->emitir($invoice)->assertStatus(422)
            ->assertJsonPath('errors.0.detail', 'El MH rechazó el DTE: 004 [identificacion.numeroControl] YA EXISTE');

        $rechazado = $invoice->fresh();
        $this->assertSame(Invoice::DTE_REJECTED, $rechazado->dte_status);
        $this->assertSame('RECHAZADO', $rechazado->mh_response['estado']);

        $this->emitir($invoice)->assertOk();

        $aceptado = $invoice->fresh();
        $this->assertNotSame($rechazado->dte_generation_code, $aceptado->dte_generation_code);
        $this->assertSame(
            [DteDocument::TRANSMITTED, DteDocument::REJECTED],
            $invoice->dteDocuments()->orderByDesc('id')->pluck('estado')->all(),
        );
        $this->assertStringEndsWith('000000000000002', $aceptado->dte_number);
        // El rechazado no se consulta: nunca tuvo validez.
        $this->assertCount(0, $this->llamadas('consultadte'));
    }

    public function test_una_respuesta_sin_sello_no_se_da_por_transmitida(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];

        // Firmado pero sin sello: no es un error, queda pendiente (202).
        $this->emitir($invoice)->assertStatus(202)
            ->assertJsonPath('data.attributes.dte_status', Invoice::DTE_PENDING);

        $this->assertSame(Invoice::DTE_PENDING, $invoice->fresh()->dte_status);
        $this->assertNull($invoice->fresh()->dte_seal);
        $this->assertSame(DteDocument::PENDING, $this->dte($invoice)->estado);
        $this->assertSame(2, $this->dte($invoice)->intentos);
        // Reintento inmediato: dos envíos antes de rendirse.
        $this->assertCount(2, $this->llamadas('/fesv/recepciondte'));
    }

    public function test_sin_respuesta_se_reenvia_el_mismo_documento_y_antes_se_consulta(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);
        $primero = $this->dte($invoice);

        // El MH sí lo había recibido: la consulta trae el sello y no se reenvía.
        $this->consultas = [['estado' => 'PROCESADO', 'sello' => self::SELLO]];
        $this->emitir($invoice)->assertOk();

        $final = $invoice->fresh();
        $this->assertSame(Invoice::DTE_ACCEPTED, $final->dte_status);
        $this->assertSame(self::SELLO, $final->dte_seal);
        $this->assertSame($primero->codigo_generacion, $final->dte_generation_code);
        $this->assertSame(1, $invoice->dteDocuments()->count());
        $this->assertCount(2, $this->llamadas('/fesv/recepciondte'));
        $this->assertSame(1, (int) DB::table('dte_sequences')->value('last_number'));
    }

    public function test_si_el_mh_no_lo_tiene_se_reenvia_el_mismo_documento(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);
        $jws = $this->dte($invoice)->firma_electronica;

        $this->emitir($invoice)->assertOk(); // consulta → 404, reenvío → PROCESADO

        $this->assertCount(1, $this->llamadas('consultadte'));
        $ultimo = collect($this->llamadas('/fesv/recepciondte'))->last();
        $this->assertSame($jws, $ultimo['documento']);
    }

    public function test_el_token_se_reutiliza_entre_envios(): void
    {
        $this->emitir($this->facturaManual())->assertOk();
        $this->emitir($this->facturaManual())->assertOk();

        $this->assertCount(1, $this->llamadas('/seguridad/auth'));
    }

    // ─── Validación previa ────────────────────────────────────────────────────

    public function test_un_emisor_incompleto_no_gasta_numero_ni_llama_al_mh(): void
    {
        $this->configurar(['dte_distrito' => '', 'dte_cod_actividad' => '7912', 'dte_nit' => '123']);
        $invoice = $this->facturaManual();

        $errores = $this->emitir($invoice)->assertStatus(422)->json('errors.*.detail');

        $this->assertContains('El NIT del emisor debe tener 9 o 14 dígitos.', $errores);
        $this->assertContains('La actividad económica no está en el catálogo CAT-019.', $errores);
        $this->assertContains('El distrito no pertenece al municipio (CAT-008).', $errores);
        $this->assertSame(Invoice::DTE_NOT_GENERATED, $invoice->fresh()->dte_status);
        $this->assertSame(0, DB::table('dte_sequences')->count());
        Http::assertNothingSent();
    }

    public function test_no_se_activa_la_facturacion_con_un_emisor_invalido(): void
    {
        $this->configurar(['dte_enabled' => false, 'dte_municipio' => '99']);
        $this->admin();

        $this->apiJson('PATCH', '/api/v1/settings', ['data' => ['type' => 'settings', 'attributes' => [
            'dte_enabled' => true, 'dte_environment' => 'production',
        ]]])->assertStatus(422)->assertJsonFragment(['El municipio no pertenece al departamento (CAT-013).']);

        // Desactivada se puede guardar a medias, y el ambiente del formulario se acepta.
        $this->apiJson('PATCH', '/api/v1/settings', ['data' => ['type' => 'settings', 'attributes' => [
            'dte_enabled' => false, 'dte_environment' => 'production',
        ]]])->assertOk();

        $this->apiJson('PATCH', '/api/v1/settings', ['data' => ['type' => 'settings', 'attributes' => [
            'dte_enabled' => true, 'dte_municipio' => '23',
        ]]])->assertOk();
    }

    public function test_la_vista_previa_no_gasta_numero(): void
    {
        $invoice = $this->facturaManual();
        $this->admin();

        $doc = $this->apiJson('GET', "/api/v1/invoices/{$invoice->id}/preview-dte")->assertOk()->json('data.attributes');

        $this->assertSame('DTE-01-M001P001-000000000000001', $doc['identificacion']['numeroControl']);
        $this->assertSame(0, DB::table('dte_sequences')->count());
        Http::assertNothingSent();
    }

    // ─── Pendientes, reintentos y bloqueo ─────────────────────────────────────

    public function test_dte_retry_reenvia_los_pendientes_que_llevan_un_rato_quietos(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);

        // Recién tocado: puede haber un envío en curso, no se compite con él.
        $this->artisan('dte:retry')->expectsOutput('Sin DTE pendientes.')->assertSuccessful();

        $this->travel(3)->minutes();
        $this->artisan('dte:retry')->expectsOutput('1 de 1 DTE pendiente(s) obtuvieron sello.')->assertSuccessful();

        $this->assertSame(DteDocument::TRANSMITTED, $this->dte($invoice)->estado);
        $this->assertSame(Invoice::DTE_ACCEPTED, $invoice->fresh()->dte_status);
        $this->assertSame(self::SELLO, $invoice->fresh()->dte_seal);
        // Antes de reenviar se preguntó al MH (404: no lo tenía).
        $this->assertCount(1, $this->llamadas('consultadte'));
        $this->assertCount(3, $this->llamadas('/fesv/recepciondte'));
    }

    public function test_un_envio_en_curso_no_se_duplica(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);

        // Otro proceso (el comando programado) está enviando este documento.
        $lock = Cache::lock('dte:send:'.$this->dte($invoice)->id, 60);
        $this->assertTrue($lock->get());

        $this->emitir($invoice)->assertStatus(202)
            ->assertJsonPath('meta.message', 'El DTE se está enviando en este momento. Vuelve a consultar en unos segundos.');
        $this->assertCount(2, $this->llamadas('/fesv/recepciondte'));

        $lock->release();
    }

    public function test_un_pendiente_se_reenvia_al_ambiente_en_que_se_firmo(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);

        $this->configurar(['dte_environment' => 'production']);
        $this->travel(3)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();

        $ultimo = collect($this->llamadas('/fesv/recepciondte'))->last();
        $this->assertStringStartsWith('https://apitest.dtes.mh.gob.sv/', $ultimo->url());
        $this->assertSame('00', $ultimo['ambiente']);
    }

    public function test_una_factura_con_dte_no_se_puede_editar(): void
    {
        $invoice = $this->facturaManual();
        $patch = fn () => $this->apiJson('PATCH', "/api/v1/invoices/{$invoice->id}", ['data' => [
            'type' => 'invoices', 'id' => (string) $invoice->id, 'attributes' => ['receptor_name' => 'Otro nombre'],
        ]]);

        // Rechazado: nunca tuvo validez, se corrige y se vuelve a emitir.
        $this->recepciones = [['estado' => 'RECHAZADO']];
        $this->emitir($invoice)->assertStatus(422);
        $patch()->assertOk();

        // Pendiente: puede llegar a sellarse en cualquier momento.
        $this->recepciones = [500, 500];
        $this->emitir($invoice)->assertStatus(202);
        $patch()->assertStatus(409);

        // Sellado.
        $this->travel(3)->minutes();
        $this->artisan('dte:retry')->assertSuccessful();
        $patch()->assertStatus(409)->assertJsonPath('errors.0.title', 'invoice.dteIssued');
        $this->assertSame('Otro nombre', $invoice->fresh()->receptor_name);
    }

    public function test_el_detalle_de_la_factura_trae_el_historial_de_dte(): void
    {
        $invoice = $this->facturaManual();
        $this->recepciones = [['estado' => 'RECHAZADO']];
        $this->emitir($invoice)->assertStatus(422);
        $this->emitir($invoice)->assertOk();

        $docs = $this->apiJson('GET', "/api/v1/invoices/{$invoice->id}")->assertOk()->json('data.attributes.dte_documents');

        $this->assertCount(2, $docs);
        $this->assertSame('transmitted', $docs[0]['estado']);
        $this->assertSame('rejected', $docs[1]['estado']);
        $this->assertSame('004 [identificacion.numeroControl] YA EXISTE', $docs[1]['ultimo_error']);
        $this->assertArrayNotHasKey('firma_electronica', $docs[0]);
    }

    public function test_al_confirmar_una_reserva_el_dte_se_emite_despues_de_la_respuesta(): void
    {
        $this->configurar(['dte_auto_generate' => true]);
        $admin = $this->admin();
        $tour = Tour::create([
            'title' => 'Volcán Santa Ana', 'description' => 'd', 'price' => 65,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ]);
        $booking = Booking::create([
            'user_id' => $admin->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(5), 'party_size' => 2, 'total_price' => 130,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        Bus::fake([TransmitInvoiceDte::class]);
        $booking->update(['status' => Booking::STATUS_CONFIRMED]);

        // La confirmación no espera al MH.
        Bus::assertDispatchedAfterResponse(TransmitInvoiceDte::class);
        Http::assertNothingSent();

        // Y el job emite; un MH caído no lo hace fallar: queda pendiente.
        $invoice = $booking->invoice()->firstOrFail();
        $this->recepciones = [500, 500];
        (new TransmitInvoiceDte($invoice))->handle(app(DteService::class));
        $this->assertSame(Invoice::DTE_PENDING, $invoice->fresh()->dte_status);
    }

    // ─── Total en letras ──────────────────────────────────────────────────────

    public function test_total_en_letras(): void
    {
        $this->assertSame('UN DÓLAR CON 00/100', AmountToWords::convert(100));
        $this->assertSame('VEINTIÚN DÓLARES CON 05/100', AmountToWords::convert(2105));
        $this->assertSame('CIEN DÓLARES CON 00/100', AmountToWords::convert(10000));
        $this->assertSame('CIENTO UN DÓLARES CON 00/100', AmountToWords::convert(10100));
        $this->assertSame('VEINTIÚN MIL DOSCIENTOS SESENTA DÓLARES CON 50/100', AmountToWords::convert(2126050));
        $this->assertSame('UN MILLÓN DE DÓLARES CON 00/100', AmountToWords::convert(100000000));
        $this->assertSame('CERO DÓLARES CON 99/100', AmountToWords::convert(99));
    }
}
