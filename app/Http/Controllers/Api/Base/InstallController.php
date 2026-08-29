<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallRequest;
use App\Services\InstallRequirementsService;
use App\Services\InstallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Instalador al estilo WordPress. Público mientras la app no esté instalada;
 * el endpoint de instalación se autobloquea una vez que existe un administrador.
 */
class InstallController extends Controller
{
    public function __construct(
        private readonly InstallService $installer,
        private readonly InstallRequirementsService $requirements,
    ) {}

    /**
     * Verificación de requisitos previos (pre-flight): versión de PHP,
     * extensiones, permisos de escritura, APP_KEY, dependencias, BD y
     * migraciones. Lo consulta el wizard antes de crear el administrador.
     */
    public function requirements(): JsonResponse
    {
        // Publica versión de PHP, extensiones, rutas escribibles y estado de la
        // BD: es un mapa del servidor. Solo tiene sentido antes de instalar.
        abort_if($this->installer->isInstalled(), 404);

        $report = $this->requirements->check();

        return response()->json([
            'data' => [
                'type' => 'install',
                'attributes' => $report,
            ],
        ]);
    }

    /**
     * Estado de instalación. Lo consulta el wizard del frontend al cargar.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'install',
                'attributes' => [
                    'installed' => $this->installer->isInstalled(),
                ],
            ],
        ]);
    }

    /**
     * Crea el primer administrador y guarda los datos del sitio.
     */
    public function install(InstallRequest $request): JsonResponse
    {
        // Cerrojo atómico: sin él, dos peticiones simultáneas pasaban ambas la
        // comprobación de "ya instalado" y creaban dos administradores.
        $lock = Cache::lock('install:run', 30);

        if (! $lock->get()) {
            return response()->json([
                'errors' => [[
                    'status' => '409',
                    'title' => 'app.installInProgress',
                    'detail' => 'Ya hay una instalación en curso.',
                ]],
            ], 409);
        }

        try {
            return $this->runInstall($request);
        } finally {
            $lock->release();
        }
    }

    private function runInstall(InstallRequest $request): JsonResponse
    {
        if ($this->installer->isInstalled()) {
            return response()->json([
                'errors' => [[
                    'status' => '409',
                    'title' => 'app.alreadyInstalled',
                    'detail' => 'La aplicación ya está instalada.',
                ]],
            ], 409);
        }

        $report = $this->requirements->check();
        if (! $report['satisfied']) {
            $failed = array_values(array_filter(
                $report['checks'],
                fn (array $c) => $c['required'] && $c['status'] === 'error',
            ));

            return response()->json([
                'errors' => [[
                    'status' => '422',
                    'title' => 'app.requirementsNotMet',
                    'detail' => 'El entorno no cumple los requisitos previos para la instalación.',
                    'meta' => ['checks' => $failed],
                ]],
            ], 422);
        }

        $user = $this->installer->install($request->validated());

        return response()->json([
            'data' => [
                'type' => 'install',
                'attributes' => [
                    'status' => true,
                    'installed' => true,
                    'message' => 'message.installed',
                    'email' => $user->email,
                ],
            ],
        ], 201);
    }
}
