<?php

namespace Tests\Feature\Base;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    private function req(string $method, string $uri, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT'  => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $payload ? json_encode($payload) : null);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'            => 'Jesus',
            'last_name'             => 'Avelar',
            'email'                 => 'admin@cusgo.test',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'site_name'             => 'Cusgo Adventures',
            'contact_email'         => 'info@cusgo.test',
            'currency'              => 'USD',
            'timezone'              => 'America/Lima',
        ], $overrides);
    }

    private function makeAdmin(): User
    {
        Role::findOrCreate('admin', 'api');
        $user = User::create([
            'username'   => 'existing',
            'first_name' => 'Ex',
            'last_name'  => 'Ist',
            'email'      => 'existing@cusgo.test',
            'password'   => bcrypt('secret123'),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_status_is_false_before_install_and_true_after(): void
    {
        $this->req('GET', '/api/v1/install/status')
            ->assertOk()
            ->assertJsonPath('data.attributes.installed', false);

        $this->makeAdmin();

        $this->req('GET', '/api/v1/install/status')
            ->assertOk()
            ->assertJsonPath('data.attributes.installed', true);
    }

    public function test_requirements_endpoint_reports_environment(): void
    {
        $response = $this->req('GET', '/api/v1/install/requirements')->assertOk();

        // En el entorno de pruebas los requisitos obligatorios se cumplen.
        $response->assertJsonPath('data.attributes.satisfied', true);

        $checks = $response->json('data.attributes.checks');
        $this->assertNotEmpty($checks);

        $keys = array_column($checks, 'key');
        $this->assertContains('php_version', $keys);
        $this->assertContains('database_connection', $keys);
        $this->assertContains('migrations', $keys);
        $this->assertContains('composer_dependencies', $keys);

        // Cada check está clasificado en uno de los tres grupos (para los pasos del wizard).
        $groups = array_unique(array_column($checks, 'group'));
        sort($groups);
        $this->assertSame(['environment', 'permissions', 'project'], $groups);

        $byGroup = [];
        foreach ($checks as $c) {
            $byGroup[$c['group']][$c['key']] = true;
        }
        $this->assertArrayHasKey('php_version', $byGroup['project']);
        $this->assertArrayHasKey('composer_dependencies', $byGroup['project']);
        $this->assertArrayHasKey('writable_storage', $byGroup['permissions']);
        $this->assertArrayHasKey('database_connection', $byGroup['environment']);
        $this->assertArrayHasKey('migrations', $byGroup['environment']);
    }

    public function test_install_creates_admin_and_site_settings(): void
    {
        $response = $this->req('POST', '/api/v1/install', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.attributes.installed', true)
            ->assertJsonPath('data.attributes.email', 'admin@cusgo.test');

        $admin = User::where('email', 'admin@cusgo.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertNotNull($admin->email_verified_at);

        $app = json_decode(Setting::where('key', 'app')->value('value') ?? '{}', true);
        $this->assertSame('Cusgo Adventures', $app['name']);
        $this->assertSame('info@cusgo.test', $app['email']);
        $this->assertSame('America/Lima', $app['timezone']);
        $this->assertTrue($app['installed']);

        $pg = json_decode(Setting::where('key', 'payment_gateway')->value('value') ?? '{}', true);
        $this->assertSame('USD', $pg['currency']);
        $this->assertSame('$', $pg['currency_symbol']);
    }

    public function test_install_seeds_base_data_and_grants_admin_all_permissions(): void
    {
        // Sin ejecutar db:seed previamente: el instalador debe dejar la app funcional.
        $this->req('POST', '/api/v1/install', $this->payload())->assertCreated();

        // Se sembraron permisos, roles y categorías de gasto.
        $this->assertGreaterThan(0, Permission::count());
        $this->assertTrue(Role::where('name', 'admin')->exists());
        $this->assertTrue(Role::where('name', 'guia')->exists());
        $this->assertGreaterThan(0, \App\Models\ExpenseCategory::count());

        // El admin recibió TODOS los permisos (p. ej. el que rompía /admin/users).
        $admin = User::where('email', 'admin@cusgo.test')->firstOrFail();
        $this->assertTrue($admin->hasPermissionTo('users:index', 'api'));
    }

    public function test_install_is_blocked_once_installed(): void
    {
        $this->makeAdmin();

        $this->req('POST', '/api/v1/install', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('errors.0.title', 'app.alreadyInstalled');

        // No se creó un segundo usuario.
        $this->assertSame(1, User::count());
    }

    public function test_install_requires_password_confirmation(): void
    {
        $this->req('POST', '/api/v1/install', $this->payload(['password_confirmation' => 'mismatch']))
            ->assertStatus(422)
            ->assertJsonFragment(['source' => ['pointer' => '/password']]);
    }

    public function test_install_requires_valid_email(): void
    {
        $this->req('POST', '/api/v1/install', $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonFragment(['source' => ['pointer' => '/email']]);
    }

    public function test_artisan_command_installs(): void
    {
        $this->artisan('app:install', [
            '--first-name'    => 'Jesus',
            '--last-name'     => 'Avelar',
            '--email'         => 'cli@cusgo.test',
            '--password'      => 'secret123',
            '--site-name'     => 'Cusgo CLI',
            '--contact-email' => 'cli-info@cusgo.test',
            '--currency'      => 'PEN',
            '--timezone'      => 'America/Lima',
        ])->assertExitCode(0);

        $admin = User::where('email', 'cli@cusgo.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('admin'));

        $pg = json_decode(Setting::where('key', 'payment_gateway')->value('value') ?? '{}', true);
        $this->assertSame('PEN', $pg['currency']);
        $this->assertSame('S/', $pg['currency_symbol']);
    }

    public function test_artisan_command_refuses_when_installed(): void
    {
        $this->makeAdmin();

        $this->artisan('app:install', [
            '--first-name' => 'X', '--last-name' => 'Y',
            '--email' => 'nope@cusgo.test', '--password' => 'secret123',
        ])->assertExitCode(1);

        $this->assertNull(User::where('email', 'nope@cusgo.test')->first());
    }
}
