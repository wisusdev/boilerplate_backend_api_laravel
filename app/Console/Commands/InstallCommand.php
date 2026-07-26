<?php

namespace App\Console\Commands;

use App\Services\InstallRequirementsService;
use App\Services\InstallService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Instalador por consola (respaldo del wizard web y útil en CI/despliegues).
 * Crea el primer administrador y guarda los datos del sitio.
 */
class InstallCommand extends Command
{
    protected $signature = 'app:install
        {--first-name= : Nombre del administrador}
        {--last-name= : Apellido del administrador}
        {--email= : Correo del administrador}
        {--password= : Contraseña del administrador}
        {--site-name= : Nombre del sitio}
        {--contact-email= : Correo de contacto del sitio}
        {--currency=USD : Código de moneda (ej. USD)}
        {--timezone=America/El_Salvador : Zona horaria}
        {--skip-requirements : Omite la verificación de requisitos previos del entorno}
        {--force : Reinstalar aunque ya exista un administrador (no elimina datos)}';

    protected $description = 'Instala la aplicación creando el primer administrador y los datos del sitio';

    public function handle(InstallService $installer, InstallRequirementsService $requirements): int
    {
        if (! $this->option('skip-requirements') && ! $this->checkRequirements($requirements)) {
            return self::FAILURE;
        }

        if ($installer->isInstalled() && ! $this->option('force')) {
            $this->error('La aplicación ya está instalada (ya existe un administrador). Usa --force para forzar.');

            return self::FAILURE;
        }

        $data = [
            'first_name' => $this->option('first-name') ?: $this->ask('Nombre del administrador'),
            'last_name' => $this->option('last-name') ?: $this->ask('Apellido del administrador'),
            'email' => $this->option('email') ?: $this->ask('Correo del administrador'),
            'password' => $this->option('password') ?: $this->secret('Contraseña (mín. 8 caracteres)'),
            'site_name' => $this->option('site-name') ?: $this->ask('Nombre del sitio', config('app.name')),
            'contact_email' => $this->option('contact-email') ?: $this->ask('Correo de contacto', null),
            'currency' => $this->option('currency'),
            'timezone' => $this->option('timezone'),
        ];

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $installer->install($validator->validated(), (bool) $this->option('force'));

        $this->newLine();
        $this->info('✔ Instalación completada.');
        $this->line("  Administrador: <comment>{$user->email}</comment> (usuario: {$user->username})");
        $this->line('  Ya puedes iniciar sesión en el panel de administración.');

        return self::SUCCESS;
    }

    /**
     * Verifica los requisitos previos del entorno y los muestra en una tabla.
     * Devuelve false si algún requisito obligatorio no se cumple.
     */
    private function checkRequirements(InstallRequirementsService $requirements): bool
    {
        $report = $requirements->check();

        $icon = fn (string $status) => match ($status) {
            'ok' => '<info>✔</info>',
            'warning' => '<comment>!</comment>',
            default => '<error>✖</error>',
        };

        $this->info('Verificando requisitos del entorno…');
        $this->table(
            ['', 'Requisito', 'Actual', 'Esperado'],
            array_map(fn (array $c) => [
                $icon($c['status']),
                $c['label'],
                $c['current'] ?? '—',
                $c['expected'] ?? '—',
            ], $report['checks']),
        );

        if (! $report['satisfied']) {
            $this->error('El entorno no cumple los requisitos obligatorios:');
            foreach ($report['checks'] as $c) {
                if ($c['required'] && $c['status'] === 'error') {
                    $this->line("  <error>•</error> {$c['label']}".($c['hint'] ? " — {$c['hint']}" : ''));
                }
            }
            $this->newLine();
            $this->line('  Corrige los puntos anteriores y vuelve a ejecutar el instalador.');
            $this->line('  (Usa <comment>--skip-requirements</comment> para omitir esta verificación bajo tu responsabilidad.)');

            return false;
        }

        $this->info('✔ Requisitos del entorno satisfechos.');
        $this->newLine();

        return true;
    }
}
