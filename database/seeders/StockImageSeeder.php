<?php

namespace Database\Seeders;

use App\Models\GalleryItem;
use App\Models\Tour;
use App\Models\TransportVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Siembra imágenes de stock (reales) para visualizar el sitio con contenido.
 * Usa Picsum (https://picsum.photos) — imágenes reales, deterministas por seed.
 * Idempotente: no re-agrega media si el modelo ya tiene; resiliente ante fallos de red.
 *
 * Ejecutar:  php artisan db:seed --class=Database\\Seeders\\StockImageSeeder
 */
class StockImageSeeder extends Seeder
{
    private function url(string $seed, int $w = 1200, int $h = 800): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    private function addFeatured($model, string $seed): void
    {
        if ($model->getFirstMedia('featured_image')) {
            return;
        }
        try {
            $model->addMediaFromUrl($this->url($seed))->toMediaCollection('featured_image');
        } catch (\Throwable $e) {
            Log::warning('StockImageSeeder featured falló', ['seed' => $seed, 'error' => $e->getMessage()]);
        }
    }

    private function addGallery($model, array $seeds): void
    {
        if ($model->getMedia('gallery')->isNotEmpty()) {
            return;
        }
        foreach ($seeds as $seed) {
            try {
                $model->addMediaFromUrl($this->url($seed, 1000, 700))->toMediaCollection('gallery');
            } catch (\Throwable $e) {
                Log::warning('StockImageSeeder gallery falló', ['seed' => $seed, 'error' => $e->getMessage()]);
            }
        }
    }

    public function run(): void
    {
        // Tours: imagen destacada para TODOS (cards reales en listado/móvil);
        // galería solo para los primeros (evita cientos de descargas a escala).
        $galleryLimitTours = 6;
        foreach (Tour::all()->values() as $index => $tour) {
            $base = $tour->slug ?: ('tour-' . $tour->id);
            $this->addFeatured($tour, $base . '-cover');
            if ($index < $galleryLimitTours) {
                $this->addGallery($tour, [$base . '-1', $base . '-2', $base . '-3']);
            }
        }

        // Vehículos: destacada para todos; galería solo para los primeros.
        $galleryLimitVehicles = 3;
        foreach (TransportVehicle::all()->values() as $index => $vehicle) {
            $base = 'vehiculo-' . $vehicle->id;
            $this->addFeatured($vehicle, $base . '-cover');
            if ($index < $galleryLimitVehicles) {
                $this->addGallery($vehicle, [$base . '-1', $base . '-2']);
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
                try {
                    $item->addMediaFromUrl($this->url('galeria-' . ($i + 1), 1200, 900))
                        ->toMediaCollection('image');
                } catch (\Throwable $e) {
                    Log::warning('StockImageSeeder galería sitio falló', ['i' => $i, 'error' => $e->getMessage()]);
                }
            }
        }
    }
}
