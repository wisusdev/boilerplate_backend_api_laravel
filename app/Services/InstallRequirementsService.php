<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Verificación de requisitos previos del instalador (pre-flight).
 *
 * Comprueba que el entorno cumple lo necesario para ejecutar la aplicación
 * Laravel antes de crear el administrador: versión de PHP, extensiones, permisos
 * de escritura de directorios, clave de la app (`APP_KEY`), dependencias
 * (`vendor`), conexión a la base de datos y migraciones ejecutadas.
 *
 * Cada comprobación devuelve un estado:
 *  - `ok`      todo correcto
 *  - `warning` no bloquea la instalación pero conviene revisar (recomendado)
 *  - `error`   bloquea: es un requisito obligatorio que no se cumple
 *
 * Cada comprobación se clasifica en un `group` para poder mostrarla por pasos:
 *  - `project`     requisitos del proyecto (PHP, extensiones, dependencias)
 *  - `permissions` permisos de escritura de directorios
 *  - `environment` configuración y datos (APP_KEY, base de datos, migraciones)
 *
 * La instalación se considera posible (`satisfied`) cuando ninguna comprobación
 * obligatoria (`required = true`) está en estado `error`.
 */
class InstallRequirementsService
{
    public const GROUP_PROJECT = 'project';
    public const GROUP_PERMISSIONS = 'permissions';
    public const GROUP_ENVIRONMENT = 'environment';

    /** Versión mínima de PHP soportada (alineada con composer.json `php: ^8.2`). */
    private const MIN_PHP_VERSION = '8.2.0';

    /**
     * Extensiones de PHP obligatorias. Combina las que Laravel exige de base con
     * las declaradas en composer.json (`ext-curl`, `ext-gd`).
     *
     * @var list<string>
     */
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash',
        'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml',
    ];

    /** Extensiones recomendadas (no bloquean la instalación). */
    private const RECOMMENDED_EXTENSIONS = ['bcmath', 'intl', 'zip'];

    /**
     * Permiso recomendado para los directorios escribibles de Laravel: `775`
     * (rwx para el propietario y el grupo, r-x para el resto), con propiedad del
     * usuario del servidor web. Se muestra en el paso de permisos como referencia.
     */
    public const RECOMMENDED_DIR_PERMISSION = '0775';

    /**
     * Ejecuta todas las comprobaciones y devuelve un informe estructurado.
     *
     * @return array{
     *   satisfied: bool,
     *   checks: list<array{key:string,group:string,label:string,status:string,required:bool,current:?string,expected:?string,hint:?string}>
     * }
     */
    public function check(): array
    {
        $checks = [
            $this->checkPhpVersion(),
            ...$this->checkExtensions(),
            ...$this->checkWritablePaths(),
            $this->checkAppKey(),
            $this->checkComposerDependencies(),
            $this->checkDatabaseConnection(),
            $this->checkMigrations(),
        ];

        $satisfied = collect($checks)
            ->every(fn (array $c) => ! ($c['required'] && $c['status'] === 'error'));

        return [
            'satisfied' => $satisfied,
            'checks'    => $checks,
        ];
    }

    /**
     * @return array{key:string,group:string,label:string,status:string,required:bool,current:?string,expected:?string,hint:?string}
     */
    private function result(
        string $key,
        string $group,
        string $label,
        string $status,
        bool $required,
        ?string $current = null,
        ?string $expected = null,
        ?string $hint = null,
    ): array {
        return compact('key', 'group', 'label', 'status', 'required', 'current', 'expected', 'hint');
    }

    private function checkPhpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');

        return $this->result(
            key: 'php_version',
            group: self::GROUP_PROJECT,
            label: 'Versión de PHP',
            status: $ok ? 'ok' : 'error',
            required: true,
            current: PHP_VERSION,
            expected: '>= ' . self::MIN_PHP_VERSION,
            hint: $ok ? null : 'Actualiza PHP a la versión mínima requerida.',
        );
    }

    /**
     * @return list<array>
     */
    private function checkExtensions(): array
    {
        $checks = [];

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = $this->result(
                key: "ext_{$ext}",
                group: self::GROUP_PROJECT,
                label: "Extensión PHP: {$ext}",
                status: $loaded ? 'ok' : 'error',
                required: true,
                current: $loaded ? 'disponible' : 'no disponible',
                expected: 'disponible',
                hint: $loaded ? null : "Instala/activa la extensión php-{$ext}.",
            );
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = $this->result(
                key: "ext_{$ext}",
                group: self::GROUP_PROJECT,
                label: "Extensión PHP: {$ext} (recomendada)",
                status: $loaded ? 'ok' : 'warning',
                required: false,
                current: $loaded ? 'disponible' : 'no disponible',
                expected: 'disponible',
                hint: $loaded ? null : "Recomendada: instala php-{$ext} para mejor rendimiento/compatibilidad.",
            );
        }

        return $checks;
    }

    /**
     * Directorios que Laravel necesita poder escribir.
     *
     * @return list<array>
     */
    private function checkWritablePaths(): array
    {
        $paths = [
            'storage'                     => storage_path(),
            'storage/framework'           => storage_path('framework'),
            'storage/framework/cache'     => storage_path('framework/cache'),
            'storage/framework/sessions'  => storage_path('framework/sessions'),
            'storage/framework/views'     => storage_path('framework/views'),
            'storage/logs'                => storage_path('logs'),
            'bootstrap/cache'             => base_path('bootstrap/cache'),
        ];

        $recommended = self::RECOMMENDED_DIR_PERMISSION;

        $checks = [];
        foreach ($paths as $label => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $mode = $exists ? $this->octalPermission($path) : null;

            // Muestra el modo octal actual junto al estado, y el recomendado como
            // referencia (p. ej. "0755 · solo lectura" frente a "0775 · con escritura").
            $current = ! $exists
                ? 'no existe'
                : $mode . ' · ' . ($writable ? 'con escritura' : 'solo lectura');

            $checks[] = $this->result(
                key: 'writable_' . str_replace('/', '_', $label),
                group: self::GROUP_PERMISSIONS,
                label: "Escritura: {$label}",
                status: $writable ? 'ok' : 'error',
                required: true,
                current: $current,
                expected: $recommended . ' · con escritura',
                hint: $writable
                    ? null
                    : "Otorga permisos de escritura a {$path} (ej. chmod -R {$recommended} y asigna la propiedad al usuario del servidor web).",
            );
        }

        return $checks;
    }

    /**
     * Permiso del directorio en formato octal de 4 dígitos (p. ej. `0775`).
     */
    private function octalPermission(string $path): string
    {
        return substr(sprintf('%04o', fileperms($path)), -4);
    }

    private function checkAppKey(): array
    {
        $key = (string) config('app.key');
        $ok = $key !== '';

        return $this->result(
            key: 'app_key',
            group: self::GROUP_ENVIRONMENT,
            label: 'Clave de la aplicación (APP_KEY)',
            status: $ok ? 'ok' : 'error',
            required: true,
            current: $ok ? 'definida' : 'ausente',
            expected: 'definida',
            hint: $ok ? null : 'Genera la clave con: php artisan key:generate',
        );
    }

    private function checkComposerDependencies(): array
    {
        $installed = is_file(base_path('vendor/autoload.php'));

        return $this->result(
            key: 'composer_dependencies',
            group: self::GROUP_PROJECT,
            label: 'Dependencias de Composer (vendor)',
            status: $installed ? 'ok' : 'error',
            required: true,
            current: $installed ? 'instaladas' : 'ausentes',
            expected: 'instaladas',
            hint: $installed ? null : 'Instala las dependencias con: composer install',
        );
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->result(
                key: 'database_connection',
                group: self::GROUP_ENVIRONMENT,
                label: 'Conexión a la base de datos',
                status: 'ok',
                required: true,
                current: 'conectada (' . config('database.default') . ')',
                expected: 'conectada',
            );
        } catch (Throwable $e) {
            return $this->result(
                key: 'database_connection',
                group: self::GROUP_ENVIRONMENT,
                label: 'Conexión a la base de datos',
                status: 'error',
                required: true,
                current: 'sin conexión',
                expected: 'conectada',
                hint: 'Revisa las credenciales DB_* en el archivo .env. Detalle: ' . $e->getMessage(),
            );
        }
    }

    private function checkMigrations(): array
    {
        try {
            $ran = Schema::hasTable('users') && Schema::hasTable('migrations');

            return $this->result(
                key: 'migrations',
                group: self::GROUP_ENVIRONMENT,
                label: 'Migraciones ejecutadas',
                status: $ran ? 'ok' : 'error',
                required: true,
                current: $ran ? 'aplicadas' : 'pendientes',
                expected: 'aplicadas',
                hint: $ran ? null : 'Ejecuta las migraciones con: php artisan migrate',
            );
        } catch (Throwable $e) {
            return $this->result(
                key: 'migrations',
                group: self::GROUP_ENVIRONMENT,
                label: 'Migraciones ejecutadas',
                status: 'error',
                required: true,
                current: 'desconocido',
                expected: 'aplicadas',
                hint: 'No se pudo verificar el esquema (¿sin conexión a BD?). Ejecuta: php artisan migrate',
            );
        }
    }
}
