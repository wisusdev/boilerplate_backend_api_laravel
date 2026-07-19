<?php

namespace App\Support\MediaLibrary;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

/**
 * Nombra los archivos al estilo WordPress: conserva el nombre original en slug
 * y le añade un sufijo corto único, para que varios archivos con el mismo
 * nombre puedan convivir en la misma carpeta año/mes/día sin sobrescribirse.
 *
 * Ej.: "Mi Foto.JPG" -> "mi-foto-a1b2c3.JPG"  (Spatie reañade la extensión).
 */
class UniqueFileNamer extends FileNamer
{
    public function originalFileName(string $fileName): string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $slug = Str::slug($name) ?: 'archivo';

        return $slug . '-' . Str::lower(Str::random(6));
    }

    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        $strippedFileName = pathinfo($fileName, PATHINFO_FILENAME);

        return "{$strippedFileName}-{$conversion->getName()}";
    }

    public function responsiveFileName(string $fileName): string
    {
        return pathinfo($fileName, PATHINFO_FILENAME);
    }
}
