<?php

namespace App\Services\Dte;

/**
 * Retención y percepción del IVA en un CCF (Código Tributario, arts. 162 y 163).
 *
 * Dependen de la calidad de cada contribuyente, así que se dejaron
 * configurables para que el negocio decida con su contador:
 *
 *  - el cliente es agente de retención (gran contribuyente designado): retiene
 *    el porcentaje sobre la venta gravada sin IVA, si la retención está activa
 *    y se alcanza el mínimo. Nunca paga percepción;
 *  - cualquier otro cliente paga percepción si vamosPues es agente de
 *    percepción (percepción activa) y se alcanza el mínimo.
 *
 * totalPagar = montoTotalOperacion − ivaRete + ivaPerci.
 */
final class IvaAjuste
{
    /** @return array{0: int, 1: int} [retención, percepción] en centavos */
    public static function calcular(DteConfig $config, bool $clienteAgenteRetencion, int $baseCents): array
    {
        if ($baseCents < $config->ivaAjusteMinimoCents()) {
            return [0, 0];
        }

        $monto = (int) round($baseCents * $config->ivaAjusteTasa() / 100);

        if ($clienteAgenteRetencion) {
            return [$config->retencionActiva() ? $monto : 0, 0];
        }

        return [0, $config->percepcionActiva() ? $monto : 0];
    }

    /**
     * Lo mismo, proporcional, para una nota de crédito o débito: ajusta la
     * operación original, así que repite lo que declaró el CCF sin volver a
     * mirar el mínimo.
     *
     * @param  array<string, mixed>  $ccfResumen
     * @return array{0: int, 1: int}
     */
    public static function comoEnCcf(DteConfig $config, array $ccfResumen, int $baseCents): array
    {
        $monto = (int) round($baseCents * $config->ivaAjusteTasa() / 100);

        return [
            ((float) ($ccfResumen['ivaRete'] ?? 0)) > 0 ? $monto : 0,
            ((float) ($ccfResumen['ivaPerci'] ?? 0)) > 0 ? $monto : 0,
        ];
    }
}
