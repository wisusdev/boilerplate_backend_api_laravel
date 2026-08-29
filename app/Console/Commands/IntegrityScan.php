<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Audita (y opcionalmente limpia) los huérfanos que ya existan en la base de
 * datos y en el disco.
 *
 * Las relaciones polimórficas (`bookings.bookable`, `payments.payable`,
 * `product_reviews.reviewable`, `media.model`) no admiten clave foránea, así que
 * este comando es la red de seguridad para los datos anteriores a las
 * correcciones de integridad.
 *
 *   php artisan integrity:scan          → solo informa
 *   php artisan integrity:scan --fix    → elimina lo que sobra
 */
class IntegrityScan extends Command
{
    protected $signature = 'integrity:scan {--fix : Elimina los registros y ficheros huérfanos encontrados}';

    protected $description = 'Busca registros y ficheros huérfanos (relaciones polimórficas, media y avatares).';

    /** Primer segmento de config('app.destination_path'), donde viven los avatares. */
    private const AVATAR_ROOT = 'uploads';

    private bool $fix = false;

    private int $total = 0;

    public function handle(): int
    {
        $this->fix = (bool) $this->option('fix');

        $this->components->info($this->fix
            ? 'Buscando y limpiando huérfanos…'
            : 'Buscando huérfanos (simulación; usa --fix para limpiar)…');

        $this->orphanBookings();
        $this->orphanPayments();
        $this->orphanProductReviews();
        $this->orphanMediaRows();
        $this->orphanMediaFiles();
        $this->orphanAvatars();

        $this->newLine();

        if ($this->total === 0) {
            $this->components->info('Sin huérfanos. La integridad está limpia.');

            return self::SUCCESS;
        }

        $this->components->warn($this->total.' elemento(s) huérfano(s) '.($this->fix ? 'eliminados.' : 'encontrados. Ejecuta con --fix para limpiarlos.'));

        return self::SUCCESS;
    }

    /** Reservas cuyo tour o vehículo ya no existe. */
    private function orphanBookings(): void
    {
        $orphans = Booking::lazy()->filter(fn (Booking $b) => $b->bookable === null)->collect();

        $this->report('Reservas sin producto (bookable)', $orphans->count(), function () use ($orphans) {
            // delete() por modelo para que BookingObserver limpie también sus pagos.
            $orphans->each->delete();
        });
    }

    /** Pagos cuya reserva ya no existe. */
    private function orphanPayments(): void
    {
        $orphans = Payment::lazy()->filter(fn (Payment $p) => $p->payable === null)->collect();

        $this->report('Pagos sin reserva (payable)', $orphans->count(), function () use ($orphans) {
            Payment::whereIn('id', $orphans->pluck('id'))->delete();
        });
    }

    /** Reseñas cuyo producto ya no existe. */
    private function orphanProductReviews(): void
    {
        $orphans = ProductReview::lazy()->filter(fn (ProductReview $r) => $r->reviewable === null)->collect();

        $this->report('Reseñas sin producto (reviewable)', $orphans->count(), function () use ($orphans) {
            ProductReview::whereIn('id', $orphans->pluck('id'))->delete();
        });
    }

    /** Filas de `media` cuyo modelo ya no existe (típico de un borrado en cascada por SQL). */
    private function orphanMediaRows(): void
    {
        $orphans = Media::lazy()->filter(function (Media $media) {
            if (! class_exists($media->model_type)) {
                return true;
            }

            return ! $media->model_type::query()->whereKey($media->model_id)->exists();
        })->collect();

        $this->report('Filas de media sin modelo', $orphans->count(), function () use ($orphans) {
            // delete() por modelo: spatie borra también el fichero y sus conversiones.
            $orphans->each->delete();
        });
    }

    /**
     * Ficheros en el disco de media que ya no corresponden a ninguna fila.
     *
     * La ruta se deriva del PathGenerator configurado (aquí,
     * App\Support\MediaLibrary\DatePathGenerator, que usa año/mes/día en vez de
     * una carpeta por id). Nunca se asume el layout por defecto de Spatie ni se
     * borran directorios completos: solo ficheros identificados uno a uno.
     */
    private function orphanMediaFiles(): void
    {
        $disk = config('media-library.disk_name', 'public');

        $valid = [];
        foreach (Media::lazy() as $media) {
            // getPath() devuelve la ruta ABSOLUTA del sistema de ficheros;
            // la que hay que comparar con allFiles() es la relativa al disco.
            $valid[ltrim($media->getPathRelativeToRoot(), '/')] = true;

            foreach (array_keys($media->generated_conversions ?? []) as $conversion) {
                $valid[ltrim($media->getPathRelativeToRoot($conversion), '/')] = true;
            }
        }

        $orphans = collect(Storage::disk($disk)->allFiles())
            ->reject(fn (string $file) => isset($valid[$file]))
            // Fuera del alcance de media: avatares y ficheros del sitio.
            ->reject(fn (string $file) => str_starts_with($file, self::AVATAR_ROOT.'/'))
            ->reject(fn (string $file) => str_starts_with($file, 'settings/'))
            ->reject(fn (string $file) => str_starts_with(basename($file), '.'))
            ->values();

        $this->report("Ficheros de media sin fila (disco '{$disk}')", $orphans->count(), function () use ($orphans, $disk) {
            Storage::disk($disk)->delete($orphans->all());
        });
    }

    /** Avatares en disco que ningún usuario referencia (incluidos los dados de baja). */
    private function orphanAvatars(): void
    {
        $referenced = User::withTrashed()
            ->whereNotNull('avatar')
            ->pluck('avatar')
            ->map(fn (string $path) => ltrim($path, '/'))
            ->flip();

        $orphans = collect(Storage::disk('public')->allFiles(self::AVATAR_ROOT))
            ->reject(fn (string $file) => $referenced->has($file));

        $this->report('Avatares en disco sin usuario', $orphans->count(), function () use ($orphans) {
            Storage::disk('public')->delete($orphans->all());
        });
    }

    private function report(string $label, int $count, callable $cleanup): void
    {
        if ($count === 0) {
            $this->components->twoColumnDetail($label, '<fg=green>0</>');

            return;
        }

        $this->total += $count;

        if ($this->fix) {
            $cleanup();
            $this->components->twoColumnDetail($label, "<fg=yellow>{$count} eliminado(s)</>");

            return;
        }

        $this->components->twoColumnDetail($label, "<fg=red>{$count}</>");
    }
}
