<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Support\LegalDocuments;
use App\Support\Seo\PageMeta;
use App\Support\SiteSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * SEO del sitio público (cuscaadventure.com). nginx del frontend deriva aquí
 * robots.txt, sitemap.xml y la carga inicial de cada ruta de la SPA: así los
 * buscadores y las vistas previas de WhatsApp/Facebook, que no ejecutan
 * JavaScript, reciben título, descripción, imagen y datos estructurados, y
 * las rutas que no existen responden 404 en vez de 200.
 */
class SeoController extends Controller
{
    /** Zonas privadas: se sirven, pero no se indexan. */
    private const PRIVATE_PREFIXES = ['/admin', '/profile', '/auth', '/account', '/pay', '/payment', '/install', '/unsubscribe'];

    /** Rutas públicas fijas de la SPA (ver src/App.tsx del frontend). */
    private const STATIC_PAGES = [
        '/' => null,
        '/tours' => ['Tours por El Salvador', 'Volcanes, playas, pueblos y cultura: elige tu próxima aventura por El Salvador.'],
        '/transport' => ['Transporte privado', 'Vehículos con conductor para moverte por El Salvador con comodidad.'],
        '/mapa' => ['Mapa de lugares y tours', 'Descubre en el mapa los lugares de nuestros tours por El Salvador.'],
    ];

    public function robots(): Response
    {
        $front = $this->frontUrl();
        $lineas = ['User-agent: *'];
        foreach (self::PRIVATE_PREFIXES as $prefijo) {
            $lineas[] = "Disallow: {$prefijo}";
        }
        $lineas[] = '';
        $lineas[] = "Sitemap: {$front}/sitemap.xml";

        return response(implode("\n", $lineas)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo:sitemap', now()->addMinutes(10), function () {
            $front = $this->frontUrl();
            $urls = [];

            foreach (array_keys(self::STATIC_PAGES) as $ruta) {
                $urls[] = ['loc' => $front.($ruta === '/' ? '/' : $ruta), 'lastmod' => null];
            }
            foreach (LegalDocuments::index() as $doc) {
                $urls[] = ['loc' => "{$front}/{$doc['slug']}", 'lastmod' => $doc['updated_at']];
            }
            foreach (Tour::query()->where('is_active', true)->orderBy('id')->get(['id', 'updated_at']) as $tour) {
                $urls[] = ['loc' => "{$front}/tours/{$tour->id}", 'lastmod' => $tour->updated_at?->toIso8601String()];
            }
            foreach (TransportVehicle::query()->where('is_active', true)->orderBy('id')->get(['id', 'updated_at']) as $vehiculo) {
                $urls[] = ['loc' => "{$front}/transport/{$vehiculo->id}", 'lastmod' => $vehiculo->updated_at?->toIso8601String()];
            }

            $cuerpo = '';
            foreach ($urls as $url) {
                $cuerpo .= '  <url><loc>'.e($url['loc']).'</loc>'
                    .($url['lastmod'] ? '<lastmod>'.e($url['lastmod']).'</lastmod>' : '')
                    ."</url>\n";
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .$cuerpo.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * La SPA con las etiquetas de la ruta pedida. Sin el build del frontend
     * responde 503 y nginx sirve el index.html estático tal cual.
     */
    public function page(?string $path = null): Response
    {
        $index = config('seo.frontend_index');
        if (! is_string($index) || ! is_readable($index)) {
            abort(503);
        }

        $ruta = '/'.trim((string) $path, '/');
        [$meta, $status] = $this->metaFor($ruta);

        return response($meta->inject((string) file_get_contents($index)), $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /** @return array{0: PageMeta, 1: int} */
    private function metaFor(string $ruta): array
    {
        $front = $this->frontUrl();
        $sitio = SiteSettings::name();
        $meta = new PageMeta(canonical: $front.($ruta === '/' ? '/' : $ruta), siteName: $sitio);

        foreach (self::PRIVATE_PREFIXES as $prefijo) {
            if ($ruta === $prefijo || str_starts_with($ruta, $prefijo.'/')) {
                $meta->noindex = true;

                return [$meta, 200];
            }
        }

        if (array_key_exists($ruta, self::STATIC_PAGES)) {
            if ($datos = self::STATIC_PAGES[$ruta]) {
                [$meta->title, $meta->description] = $datos;
            } else {
                $meta->jsonLd[] = $this->organizacion();
            }

            return [$meta, 200];
        }

        if (LegalDocuments::exists($slug = ltrim($ruta, '/'))) {
            $meta->title = LegalDocuments::DOCUMENTS[$slug];
            $meta->description = LegalDocuments::DOCUMENTS[$slug].' de '.$sitio.'.';

            return [$meta, 200];
        }

        if (preg_match('#^/tours/([^/]+)$#', $ruta, $m) && $tour = $this->tour($m[1])) {
            return [$this->tourMeta($meta, $tour), 200];
        }

        if (preg_match('#^/transport/(\d+)$#', $ruta, $m)
            && $vehiculo = TransportVehicle::query()->where('is_active', true)->find($m[1])) {
            $meta->title = $vehiculo->title;
            $meta->description = PageMeta::summary((string) $vehiculo->description);
            $meta->image = $vehiculo->getFirstMedia('featured_image')?->getUrl();

            return [$meta, 200];
        }

        $meta->title = 'Página no encontrada';
        $meta->noindex = true;
        $meta->canonical = null;

        return [$meta, 404];
    }

    private function tour(string $clave): ?Tour
    {
        return Tour::query()
            ->where(is_numeric($clave) ? 'id' : 'slug', $clave)
            ->where('is_active', true)
            ->first();
    }

    private function tourMeta(PageMeta $meta, Tour $tour): PageMeta
    {
        $imagen = $tour->getFirstMedia('featured_image')?->getUrl();
        $meta->title = $tour->title;
        $meta->description = PageMeta::summary((string) $tour->description);
        $meta->image = $imagen;
        $meta->type = 'product';
        // Un solo canónico por tour aunque se llegue por el slug.
        $meta->canonical = $this->frontUrl().'/tours/'.$tour->id;

        $viaje = [
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $tour->title,
            'description' => $meta->description,
            'url' => $meta->canonical,
            'touristType' => 'Aventura',
            'provider' => ['@type' => 'TravelAgency', 'name' => SiteSettings::name(), 'url' => $this->frontUrl()],
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format((float) $tour->price, 2, '.', ''),
                'priceCurrency' => SiteSettings::currency(),
                'availability' => 'https://schema.org/InStock',
                'url' => $meta->canonical,
            ],
        ];
        if ($imagen) {
            $viaje['image'] = $imagen;
        }
        if (($resenas = $tour->reviewsCount()) > 0) {
            $viaje['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $tour->averageRating(), 1),
                'reviewCount' => $resenas,
            ];
        }
        $meta->jsonLd[] = $viaje;

        return $meta;
    }

    /** La agencia, en la portada. */
    private function organizacion(): array
    {
        $app = json_decode((string) optional(Setting::query()->where('key', 'app')->first())->value, true) ?: [];
        $texto = fn (string $clave) => is_string($app[$clave] ?? null) && trim($app[$clave]) !== '' ? trim($app[$clave]) : null;

        $agencia = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'TravelAgency',
            'name' => SiteSettings::name(),
            'url' => $this->frontUrl(),
            'logo' => $texto('app_logo_dark_url') ?? $texto('app_logo_url'),
            'email' => $texto('contact_email'),
            'telephone' => $texto('contact_phone'),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $texto('contact_address'),
                'addressLocality' => $texto('contact_city'),
                'addressCountry' => $texto('contact_country') ?? 'SV',
            ]),
            'sameAs' => array_values(array_filter(array_map($texto, [
                'social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'social_twitter',
            ]))),
        ]);

        return $agencia;
    }

    private function frontUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
