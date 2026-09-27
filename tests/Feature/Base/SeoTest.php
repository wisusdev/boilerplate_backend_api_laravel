<?php

namespace Tests\Feature\Base;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * robots.txt, sitemap.xml y la SPA servida con las etiquetas de cada página.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private string $index;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['app.frontend_url' => 'https://cusgo.test']);

        $this->index = tempnam(sys_get_temp_dir(), 'index').'.html';
        file_put_contents($this->index, <<<'HTML'
            <!doctype html>
            <html lang="es">
              <head>
                <title>Cusgo Adventures — Aventura auténtica en El Salvador</title>
                <meta name="description" content="Tours de aventura en El Salvador." />
                <meta property="og:title" content="Genérico" />
                <meta property="og:image" content="https://cusgo.test/og-default.jpg" />
              </head>
              <body><div id="root"></div></body>
            </html>
            HTML);
        config(['seo.frontend_index' => $this->index]);
    }

    protected function tearDown(): void
    {
        @unlink($this->index);
        parent::tearDown();
    }

    private function tour(array $attrs = []): Tour
    {
        return Tour::create(array_merge([
            'title' => 'Volcán de Santa Ana', 'description' => '<p>Caminata al cráter con laguna turquesa.</p>', 'price' => 55,
            'max_capacity' => 10, 'location' => 'Santa Ana', 'currency_code' => 'USD', 'is_active' => true,
        ], $attrs));
    }

    public function test_robots_bloquea_las_zonas_privadas_y_apunta_al_sitemap(): void
    {
        $this->get('/seo/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /pay', false)
            ->assertSee('Sitemap: https://cusgo.test/sitemap.xml', false);
    }

    public function test_el_robots_de_la_api_no_deja_indexar_nada(): void
    {
        $this->assertStringContainsString('Disallow: /', (string) file_get_contents(public_path('robots.txt')));
    }

    public function test_el_sitemap_lista_paginas_legales_y_solo_tours_activos(): void
    {
        $activo = $this->tour();
        $borrador = $this->tour(['title' => 'Borrador', 'is_active' => false]);

        $xml = $this->get('/seo/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>https://cusgo.test/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://cusgo.test/terminos</loc>', $xml);
        $this->assertStringContainsString("<loc>https://cusgo.test/tours/{$activo->id}</loc>", $xml);
        $this->assertStringNotContainsString("/tours/{$borrador->id}<", $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_la_pagina_de_un_tour_lleva_sus_etiquetas_y_datos_estructurados(): void
    {
        $tour = $this->tour();

        $html = $this->get("/seo/page/tours/{$tour->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('<title>Volcán de Santa Ana · ', $html);
        $this->assertStringContainsString('<meta name="description" content="Caminata al cráter con laguna turquesa." />', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Volcán de Santa Ana · ', $html);
        // Por slug o por id, un único canónico.
        $this->assertStringContainsString("<link rel=\"canonical\" href=\"https://cusgo.test/tours/{$tour->id}\" />", $html);
        $this->assertStringContainsString('"@type":"TouristTrip"', $html);
        $this->assertStringContainsString('"price":"55.00"', $html);
        // Las genéricas desaparecen: una sola etiqueta de cada.
        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertStringNotContainsString('content="Genérico"', $html);
        $this->assertStringContainsString('<div id="root"></div>', $html);
    }

    public function test_la_portada_conserva_su_titulo_y_describe_a_la_agencia(): void
    {
        $html = $this->get('/seo/page')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Cusgo Adventures — Aventura auténtica en El Salvador</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://cusgo.test/" />', $html);
        $this->assertStringContainsString('"@type":"TravelAgency"', $html);
    }

    public function test_una_ruta_inexistente_o_un_tour_inactivo_dan_404_sin_indexar(): void
    {
        $inactivo = $this->tour(['is_active' => false]);

        foreach (['/seo/page/no-existe', "/seo/page/tours/{$inactivo->id}", '/seo/page/tours/999'] as $url) {
            $this->get($url)
                ->assertNotFound()
                ->assertSee('<meta name="robots" content="noindex, nofollow" />', false)
                ->assertSee('<div id="root"></div>', false);
        }
    }

    public function test_las_zonas_privadas_se_sirven_sin_indexar(): void
    {
        $this->get('/seo/page/admin/tours')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow" />', false);
    }

    public function test_un_titulo_con_html_no_rompe_la_pagina(): void
    {
        $tour = $this->tour(['title' => '</script><script>alert(1)</script> "Tour"']);

        $html = $this->get("/seo/page/tours/{$tour->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;/script&gt;', $html);
    }

    public function test_sin_el_build_del_frontend_responde_503(): void
    {
        config(['seo.frontend_index' => '/no/existe/index.html']);

        $this->get('/seo/page/tours')->assertStatus(503);
    }
}
