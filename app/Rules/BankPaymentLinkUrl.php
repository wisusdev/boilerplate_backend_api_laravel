<?php

namespace App\Rules;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida la URL de un enlace de pago pegada en el panel.
 *
 * El enlace lo teclea una persona y después se lo enviamos al cliente por correo
 * y por WhatsApp, firmado con nuestra marca. Eso convierte un error de copiado
 * —o una sesión de panel comprometida— en phishing con nuestra credibilidad
 * como aval. Por eso el destino se restringe a una lista blanca en vez de
 * aceptar cualquier `https://`.
 */
class BankPaymentLinkUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);

        $partes = parse_url($url);

        if ($partes === false || empty($partes['host'])) {
            $fail('El enlace no es una dirección web válida.');

            return;
        }

        // Solo https: el cliente va a introducir una tarjeta al otro lado.
        if (strtolower($partes['scheme'] ?? '') !== 'https') {
            $fail('El enlace debe empezar por https://.');

            return;
        }

        // `https://banco.com@malicioso.com/` se lee como el host del final. Es el
        // truco de suplantación más viejo que hay y a simple vista pasa.
        if (isset($partes['user']) || isset($partes['pass'])) {
            $fail('El enlace no puede llevar credenciales incrustadas.');

            return;
        }

        $host = strtolower($partes['host']);
        $permitidos = SiteSettings::bacLinkHosts();

        foreach ($permitidos as $permitido) {
            if ($host === $permitido || str_ends_with($host, '.'.$permitido)) {
                return;
            }
        }

        // Se nombra el host rechazado a propósito: si el banco emite desde un
        // dominio que no teníamos previsto, el agente necesita saber cuál añadir
        // en Ajustes en vez de quedarse bloqueado adivinando.
        $fail(sprintf(
            'El enlace apunta a «%s», que no está en la lista de dominios autorizados (%s). '
            .'Si el banco emite desde ese dominio, añádelo en Ajustes → Pagos.',
            $host,
            implode(', ', $permitidos)
        ));
    }
}
