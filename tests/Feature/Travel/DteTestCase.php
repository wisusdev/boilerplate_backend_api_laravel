<?php

namespace Tests\Feature\Travel;

use App\Models\DteDocument;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\DteService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

/**
 * Escenario común de los tests de facturación electrónica: emisor completo,
 * certificado de prueba y un MH simulado que responde lo que cada test pone en
 * sus colas.
 */
abstract class DteTestCase extends TestCase
{
    use RefreshDatabase;

    protected const SELLO = '2026A1B2C3D4E5F6A7B8C9D0E1F2A3B4C5D6E7F8';

    protected const SELLO_EVENTO = '2026FFFFEEEEDDDDCCCCBBBBAAAA999988887777';

    /** El MH contesta, pero sin sello ni rechazo: no es un recibo. */
    protected const SIN_SELLO = ['estado' => 'PROCESADO'];

    protected \OpenSSLAsymmetricKey $key;

    /** @var list<array{estado: string, sello?: string}|int> */
    protected array $recepciones = [];

    /** @var list<array<string, mixed>|int> */
    protected array $consultas = [];

    /** @var list<array<string, mixed>|int> Respuestas a /fesv/anulardte. */
    protected array $anulaciones = [];

    /** @var list<array<string, mixed>|int> Respuestas crudas a /fesv/contingencia. */
    protected array $eventosContingencia = [];

    /** @var list<array<string, mixed>|int> Respuestas crudas a /fesv/recepcionlote/. */
    protected array $lotes = [];

    /** @var list<array<string, mixed>|int> Respuestas crudas a la consulta del lote. */
    protected array $consultasLote = [];

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

    protected function configurar(array $extra = []): void
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
            'dte_responsable_nombre' => 'Ana Martínez',
            'dte_responsable_tipo_doc' => '13',
            'dte_responsable_num_doc' => '01234567-8',
            'dte_mh_password' => Crypt::encryptString('clave-api-mh'),
        ], $extra))]);

        $this->cargarCertificado();
    }

    protected function cargarCertificado(): void
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
    protected function fakeMh(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/seguridad/auth')) {
                return Http::response(['status' => 'OK', 'body' => ['token' => 'Bearer token-de-prueba']]);
            }
            // Antes que consultadte, que es prefijo de esta ruta.
            if (str_contains($request->url(), '/fesv/recepcion/consultadtelote/')) {
                return $this->cruda(array_shift($this->consultasLote) ?? ['procesados' => [], 'rechazados' => []]);
            }
            if (str_contains($request->url(), '/fesv/recepcion/consultadte')) {
                return $this->respuesta(array_shift($this->consultas) ?? 404);
            }
            if (str_ends_with($request->url(), '/fesv/recepciondte')) {
                return $this->respuesta(array_shift($this->recepciones) ?? ['estado' => 'PROCESADO', 'sello' => self::SELLO]);
            }
            if (str_ends_with($request->url(), '/fesv/anulardte')) {
                return $this->respuesta(array_shift($this->anulaciones) ?? ['estado' => 'PROCESADO', 'sello' => self::SELLO_EVENTO]);
            }
            if (str_ends_with($request->url(), '/fesv/contingencia')) {
                return $this->cruda(array_shift($this->eventosContingencia)
                    ?? ['estado' => 'RECIBIDO', 'selloRecibido' => self::SELLO_EVENTO, 'mensaje' => 'EVENTO RECIBIDO']);
            }
            if (str_ends_with($request->url(), '/fesv/recepcionlote/')) {
                return $this->cruda(array_shift($this->lotes) ?? ['estado' => 'RECIBIDO', 'codigoLote' => 'LOTE-0001']);
            }

            return Http::response('no encontrado', 404);
        });
    }

    protected function cruda(array|int $r)
    {
        return is_int($r) ? Http::response('<html>error</html>', $r) : Http::response($r);
    }

    protected function respuesta(array|int $r)
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

    protected function admin(): User
    {
        $user = User::create([
            'username' => 'dte'.uniqid(), 'first_name' => 'Admin', 'last_name' => 'DTE',
            'email' => uniqid('dte').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('admin');
        Passport::actingAs($user->fresh());

        return $user;
    }

    protected function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    protected function facturaManual(?array $items = null, array $receptor = []): Invoice
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

    protected function emitir(Invoice $invoice): TestResponse
    {
        return $this->apiJson('POST', "/api/v1/invoices/{$invoice->id}/generate-dte");
    }

    /** El DTE vigente (el último) de una factura. */
    protected function dte(Invoice $invoice): DteDocument
    {
        return $invoice->dteDocuments()->latest('id')->firstOrFail();
    }

    protected function documento(Invoice $invoice): array
    {
        return $this->dte($invoice)->document();
    }

    protected function assertCumpleEsquema(array $documento, string $esquema = 'fe-f-v2.json'): void
    {
        // El MH lee el texto JSON ("141.3") como decimal. Con su escala por
        // defecto (14) opis compara el binario 141.30000000000001 y falla el
        // multipleOf 0.01; 8 decimales es el multipleOf más fino del esquema.
        Helper::$numberScale = 8;
        $validator = new Validator;
        $validator->setMaxErrors(20);
        $result = $validator->validate(
            json_decode(json_encode($documento)),
            file_get_contents(base_path("tests/Fixtures/dte-schemas/{$esquema}")),
        );

        $this->assertTrue(
            $result->isValid(),
            $result->isValid() ? '' : json_encode((new ErrorFormatter)->format($result->error()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /** @return list<Request> */
    protected function llamadas(string $ruta): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), $ruta))->map(fn ($par) => $par[0])->values()->all();
    }
}
