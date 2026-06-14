<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando la firma de un webhook de pago no se puede verificar.
 * El controlador la traduce a un HTTP 400 para que el gateway reintente.
 */
class InvalidWebhookSignatureException extends RuntimeException
{
}
