<?php

namespace App\Services\Dte;

use RuntimeException;

/** El MH no dio una respuesta útil: red caída, timeout, 5xx o un cuerpo que no es un resultado. */
class MhUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
