<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64FileValidationRule implements ValidationRule
{
    private array $allowedMimeTypes;
    private int $maxSize;

    public function __construct(array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], int $maxSize = 2048)
    {
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxSize = $maxSize; // KB
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Verificar si el valor está vacío
        if (empty($value)) {
            return;
        }

        // Verificar formato base64 con data URI
        if (!preg_match('/^data:([a-zA-Z0-9][a-zA-Z0-9\/+]*);base64,(.+)$/', $value, $matches)) {
            $fail(__('validation.invalid_base64_format', ['attribute' => $attribute]));
            return;
        }

        $mimeType = $matches[1];
        $base64Data = $matches[2];

        // Verificar tipo MIME
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            $fail(__('validation.invalid_file_type', [
                'attribute' => $attribute,
                'allowed' => implode(', ', $this->allowedMimeTypes)
            ]));
            return;
        }

        // Verificar que sea base64 válido
        $decodedData = base64_decode($base64Data, true);
        if ($decodedData === false || base64_encode($decodedData) !== $base64Data) {
            $fail(__('validation.invalid_base64', ['attribute' => $attribute]));
            return;
        }

        // Verificar tamaño del archivo
        $fileSizeKB = strlen($decodedData) / 1024;
        if ($fileSizeKB > $this->maxSize) {
            $fail(__('validation.file_too_large', [
                'attribute' => $attribute,
                'max' => $this->maxSize
            ]));
            return;
        }

        // Verificar que el contenido coincida con el tipo MIME declarado
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_buffer($finfo, $decodedData);
        finfo_close($finfo);

        if ($actualMimeType !== $mimeType) {
            $fail(__('validation.mime_type_mismatch', ['attribute' => $attribute]));
            return;
        }
    }
}