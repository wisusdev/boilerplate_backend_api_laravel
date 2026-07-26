<?php

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Organiza los archivos multimedia al estilo WordPress: año/mes/día,
 * en lugar de crear una carpeta por cada media ({id}/).
 *
 * La unicidad de los nombres (para que varios archivos convivan en la misma
 * carpeta de fecha) la garantiza App\Support\MediaLibrary\UniqueFileNamer.
 */
class DatePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'/responsive-images/';
    }

    protected function basePath(Media $media): string
    {
        // created_at ya existe cuando Spatie almacena el archivo; now() es un
        // resguardo para casos límite (media aún no persistida).
        $date = $media->created_at ?? now();
        $path = $date->format('Y/m/d');

        $prefix = config('media-library.prefix', '');

        return $prefix !== '' ? $prefix.'/'.$path : $path;
    }
}
