<?php

namespace App\Services\Dte;

use RuntimeException;

/** Un DTE que no se pudo emitir. `errors` trae la lista completa cuando hay varias causas. */
class DteException extends RuntimeException
{
    /** @param  list<string>  $errors */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @param  list<string>  $errors */
    public static function invalid(array $errors): self
    {
        return new self('Faltan datos para emitir el DTE: '.implode(' ', $errors), $errors);
    }
}
