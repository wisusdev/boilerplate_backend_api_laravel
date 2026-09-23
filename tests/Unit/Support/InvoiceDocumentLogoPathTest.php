<?php

namespace Tests\Unit\Support;

use App\Support\InvoiceDocument;
use Tests\TestCase;

class InvoiceDocumentLogoPathTest extends TestCase
{
    // logoPath() resuelve directamente sobre storage_path('app/public/...'),
    // sin pasar por el disco configurado, así que Storage::fake('public') no
    // lo interceptaría: se usa un fichero real dentro de un subdirectorio
    // propio del test, que se limpia en tearDown.
    private string $subdir = 'settings-test-logo';

    protected function tearDown(): void
    {
        $dir = storage_path('app/public/'.$this->subdir);

        if (is_dir($dir)) {
            array_map('unlink', glob($dir.'/*') ?: []);
            rmdir($dir);
        }

        parent::tearDown();
    }

    public function test_resuelve_un_logo_real_dentro_de_storage_app_public(): void
    {
        $dir = storage_path('app/public/'.$this->subdir);
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/logo-dark.png', 'contenido-falso-de-imagen');

        $ruta = InvoiceDocument::logoPath([
            'app_logo_dark_url' => "https://ejemplo.test/storage/{$this->subdir}/logo-dark.png",
        ]);

        $this->assertSame(realpath($dir.'/logo-dark.png'), $ruta);
    }

    public function test_rechaza_traversal_fuera_de_storage_app_public(): void
    {
        // Un fichero que sí existe, pero fuera de storage/app/public (p. ej.
        // dentro del proyecto), no debe poder alcanzarse desde el ajuste de logo.
        $rutaSensible = base_path('composer.json');
        $this->assertFileExists($rutaSensible);

        $ruta = InvoiceDocument::logoPath([
            'app_logo_dark_url' => 'https://ejemplo.test/storage/../../composer.json',
        ]);

        $this->assertNull($ruta);
    }

    public function test_null_cuando_no_hay_ajuste_de_logo(): void
    {
        $this->assertNull(InvoiceDocument::logoPath([]));
    }

    public function test_null_cuando_el_fichero_resuelto_no_existe(): void
    {
        $ruta = InvoiceDocument::logoPath([
            'app_logo_dark_url' => 'https://ejemplo.test/storage/settings/no-existe.png',
        ]);

        $this->assertNull($ruta);
    }
}
