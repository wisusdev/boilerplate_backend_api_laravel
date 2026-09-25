<?php

namespace App\Services\Dte;

/**
 * Respuesta de recepción o consulta del MH. Un DTE solo tiene validez
 * tributaria con sello de recepción: cualquier otra respuesta no lo es.
 */
final class MhResult
{
    /** @param  array<string, mixed>  $body */
    private function __construct(
        public readonly string $estado,
        public readonly string $sello,
        public readonly array $body,
    ) {}

    /** @param  array<string, mixed>  $body */
    public static function fromResponse(array $body): self
    {
        return new self(
            strtoupper((string) ($body['estado'] ?? '')),
            (string) ($body['selloRecibido'] ?? ''),
            $body,
        );
    }

    public function rejected(): bool
    {
        return $this->estado === 'RECHAZADO';
    }

    public function accepted(): bool
    {
        return $this->sello !== '' && ! $this->rejected();
    }

    /** Mensaje legible del rechazo: código, descripción y observaciones. */
    public function message(): string
    {
        $msg = trim(($this->body['codigoMsg'] ?? '').' '.($this->body['descripcionMsg'] ?? ''));
        $obs = array_filter((array) ($this->body['observaciones'] ?? []));

        return $obs ? trim($msg.' — '.implode('; ', $obs)) : $msg;
    }
}
