<?php

namespace Database\Seeders;

use App\Models\GalleryItem;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Support\PlaceholderImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Siembra imágenes para visualizar el sitio con contenido.
 *
 * Por defecto se generan en local con GD: instantáneo, determinista y sin
 * conexión. Antes se descargaban de picsum.photos, y cuando ese servicio se cae
 * cada imagen agota su timeout: sembrar pasaba de segundos a más de una hora, y
 * dejaba el catálogo sin fotos.
 *
 * Para usar imágenes reales, define en el .env una plantilla de URL con los
 * marcadores {seed}, {w} y {h}; si la descarga falla se cae a la imagen local,
 * de modo que el catálogo nunca se queda vacío:
 *
 *   SEED_IMAGE_URL="https://picsum.photos/seed/{seed}/{w}/{h}"
 *
 * Ejecutar:  php artisan db:seed --class=Database\\Seeders\\StockImageSeeder
 */
class StockImageSeeder extends Seeder
{
    /** Plantilla de URL remota, si se configuró. */
    private function plantilla(): ?string
    {
        $valor = trim((string) env('SEED_IMAGE_URL', ''));

        return $valor !== '' ? $valor : null;
    }

    /**
     * Adjunta una imagen a la colección indicada. Intenta la fuente remota si
     * está configurada y, si falla, genera la imagen en local.
     */
    private function adjuntar($model, string $coleccion, string $seed, int $w, int $h, string $rotulo): void
    {
        $plantilla = $this->plantilla();

        if ($plantilla) {
            $url = strtr($plantilla, ['{seed}' => $seed, '{w}' => (string) $w, '{h}' => (string) $h]);
            try {
                $model->addMediaFromUrl($url)->toMediaCollection($coleccion);

                return;
            } catch (\Throwable $e) {
                Log::warning('StockImageSeeder: descarga fallida, se genera en local.', [
                    'seed' => $seed, 'error' => $e->getMessage(),
                ]);
            }
        }

        $ruta = PlaceholderImage::tempFile($seed, $w, $h, $rotulo);
        try {
            $model->addMedia($ruta)->toMediaCollection($coleccion);
        } finally {
            // addMedia mueve el fichero; si algo falló antes, se limpia igual.
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }

    private function addFeatured($model, string $seed, string $rotulo): void
    {
        if ($model->getFirstMedia('featured_image')) {
            return;
        }

        $this->adjuntar($model, 'featured_image', $seed, 1200, 800, $rotulo);
    }

    private function addGallery($model, array $seeds, string $rotulo): void
    {
        if ($model->getMedia('gallery')->isNotEmpty()) {
            return;
        }

        foreach ($seeds as $seed) {
            $this->adjuntar($model, 'gallery', $seed, 1000, 700, $rotulo);
        }
    }

    public function run(): void
    {
        // Tours: imagen destacada para TODOS (cards reales en listado/móvil);
        // galería solo para los primeros (evita cientos de descargas a escala).
        $galleryLimitTours = 6;
        foreach (Tour::all()->values() as $index => $tour) {
            $base = $tour->slug ?: ('tour-'.$tour->id);
            $this->addFeatured($tour, $base.'-cover', $tour->title);
            if ($index < $galleryLimitTours) {
                $this->addGallery($tour, [$base.'-1', $base.'-2', $base.'-3'], $tour->title);
            }
        }

        // Vehículos: destacada para todos; galería solo para los primeros.
        $galleryLimitVehicles = 3;
        foreach (TransportVehicle::all()->values() as $index => $vehicle) {
            $base = 'vehiculo-'.$vehicle->id;
            $this->addFeatured($vehicle, $base.'-cover', $vehicle->title);
            if ($index < $galleryLimitVehicles) {
                $this->addGallery($vehicle, [$base.'-1', $base.'-2'], $vehicle->title);
            }
        }

        // Galería del sitio (si está vacía)
        if (GalleryItem::count() === 0) {
            $captions = [
                'Amanecer en el cráter',
                'Olas del Pacífico',
                'Café de altura',
                'Sendero al bosque nuboso',
                'Pueblos coloniales',
                'Noche volcánica',
                'Cascada escondida',
                'Vida local',
            ];
            foreach ($captions as $i => $caption) {
                $item = GalleryItem::create(['caption' => $caption, 'sort_order' => $i + 1]);
                $this->adjuntar($item, 'image', 'galeria-'.($i + 1), 1200, 900, $caption);
            }
        }
    }
}
