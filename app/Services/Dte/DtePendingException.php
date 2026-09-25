<?php

namespace App\Services\Dte;

/**
 * El DTE está firmado pero todavía sin sello: el MH no respondió o hay un
 * envío en curso. No es un fallo definitivo; se reintenta solo.
 */
class DtePendingException extends DteException {}
