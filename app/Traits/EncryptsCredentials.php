<?php

namespace App\Traits;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Proporciona cifrado AES-256-CBC (vía APP_KEY) para credenciales sensibles
 * almacenadas en la tabla settings.
 *
 * - encryptCredential(): cifra un valor solo si no está vacío.
 * - decryptCredential(): descifra con fallback a texto plano para compatibilidad
 *   con valores anteriores al cifrado (no doble-encriptado).
 */
trait EncryptsCredentials
{
    protected function encryptCredential(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    protected function decryptCredential(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Valor almacenado antes del cifrado — retornar tal cual (legacy).
            return $value;
        }
    }
}
