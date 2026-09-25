<?php

namespace App\Services\Dte;

use App\Models\Setting;
use App\Support\Dte\SvCatalogs;
use App\Traits\EncryptsCredentials;

/**
 * Configuración del emisor, leída del ajuste `dte` y normalizada.
 *
 * Las contraseñas se guardan cifradas (SettingsController) y aquí se
 * descifran: el servicio nunca debe trabajar con el texto cifrado.
 */
class DteConfig
{
    use EncryptsCredentials;

    public const AMBIENTE_PRUEBAS = '00';

    public const AMBIENTE_PRODUCCION = '01';

    /** @param  array<string, mixed>  $raw */
    private function __construct(private readonly array $raw) {}

    public static function load(): self
    {
        $row = Setting::where('key', 'dte')->first();

        return new self(json_decode($row?->value ?? '{}', true) ?? []);
    }

    /** @param  array<string, mixed>  $raw */
    public static function fromArray(array $raw): self
    {
        return new self($raw);
    }

    public function enabled(): bool
    {
        return (bool) ($this->raw['dte_enabled'] ?? false);
    }

    public function autoGenerate(): bool
    {
        return (bool) ($this->raw['dte_auto_generate'] ?? false);
    }

    public function ambiente(): string
    {
        return in_array($this->raw['dte_environment'] ?? 'test', ['production', self::AMBIENTE_PRODUCCION], true)
            ? self::AMBIENTE_PRODUCCION
            : self::AMBIENTE_PRUEBAS;
    }

    public function nit(): string
    {
        return self::digits($this->raw['dte_nit'] ?? '');
    }

    public function nrc(): string
    {
        return self::digits($this->raw['dte_nrc'] ?? '');
    }

    public function nombre(): string
    {
        return trim((string) ($this->raw['dte_nombre'] ?? ''));
    }

    public function nombreComercial(): string
    {
        return trim((string) ($this->raw['dte_nombre_comercial'] ?? ''));
    }

    public function codActividad(): string
    {
        return trim((string) ($this->raw['dte_cod_actividad'] ?? ''));
    }

    /** La descripción es siempre la del catálogo, para que nunca discrepe del código. */
    public function descActividad(): string
    {
        return SvCatalogs::actividad($this->codActividad())
            ?? trim((string) ($this->raw['dte_desc_actividad'] ?? ''));
    }

    public function departamento(): string
    {
        return trim((string) ($this->raw['dte_departamento'] ?? ''));
    }

    public function municipio(): string
    {
        return trim((string) ($this->raw['dte_municipio'] ?? ''));
    }

    public function distrito(): string
    {
        return trim((string) ($this->raw['dte_distrito'] ?? ''));
    }

    public function direccion(): string
    {
        return trim((string) ($this->raw['dte_direccion'] ?? ''));
    }

    public function telefono(): string
    {
        return trim((string) ($this->raw['dte_telefono'] ?? ''));
    }

    public function correo(): string
    {
        return trim((string) ($this->raw['dte_correo'] ?? ''));
    }

    /** CAT-009. Por defecto 02, casa matriz. */
    public function tipoEstablecimiento(): string
    {
        return (string) ($this->raw['dte_tipo_establecimiento'] ?? '') ?: '02';
    }

    /**
     * Código de establecimiento de 3 dígitos (por defecto 001). Se acepta
     * también el formato antiguo del formulario ("M001"): la letra la pone el
     * tipo de establecimiento, no el usuario.
     */
    public function codEstable(): string
    {
        return self::threeDigits($this->raw['dte_cod_establec'] ?? '');
    }

    public function codPuntoVenta(): string
    {
        return self::threeDigits($this->raw['dte_cod_punto_venta'] ?? '');
    }

    /** Código interno del establecimiento con su letra: "M001". */
    public function codEstableCompleto(): string
    {
        return SvCatalogs::establecimientoLetter($this->tipoEstablecimiento()).$this->codEstable();
    }

    /** Código interno del punto de venta: "P001". */
    public function codPuntoVentaCompleto(): string
    {
        return 'P'.$this->codPuntoVenta();
    }

    /** Código que el MH asignó al establecimiento; si no se registró, el interno. */
    public function codEstableMH(): string
    {
        $mh = strtoupper(trim((string) ($this->raw['dte_cod_estable_mh'] ?? '')));

        return strlen($mh) === 4 ? $mh : $this->codEstableCompleto();
    }

    public function codPuntoVentaMH(): string
    {
        $mh = strtoupper(trim((string) ($this->raw['dte_cod_punto_venta_mh'] ?? '')));

        return strlen($mh) === 4 ? $mh : $this->codPuntoVentaCompleto();
    }

    /**
     * Responsable del establecimiento: firma el evento de contingencia y es
     * quien realiza, por defecto, las invalidaciones.
     */
    public function responsableNombre(): string
    {
        return trim((string) ($this->raw['dte_responsable_nombre'] ?? ''));
    }

    public function responsableTipoDoc(): string
    {
        return (string) ($this->raw['dte_responsable_tipo_doc'] ?? '');
    }

    public function responsableNumDoc(): string
    {
        return trim((string) ($this->raw['dte_responsable_num_doc'] ?? ''));
    }

    /** vamosPues es agente de percepción: cobra el 1 % a los clientes que no son grandes contribuyentes. */
    public function percepcionActiva(): bool
    {
        return (bool) ($this->raw['dte_percepcion_activa'] ?? false);
    }

    /** Se acepta que los clientes agentes de retención retengan el 1 %. */
    public function retencionActiva(): bool
    {
        return (bool) ($this->raw['dte_retencion_activa'] ?? false);
    }

    /** Designado agente de retención por Hacienda: puede emitir comprobantes de retención (07). */
    public function agenteRetencion(): bool
    {
        return (bool) ($this->raw['dte_agente_retencion'] ?? false);
    }

    /** Porcentaje de retención o percepción (por defecto 1 %). */
    public function ivaAjusteTasa(): float
    {
        $tasa = (float) ($this->raw['dte_iva_ajuste_tasa'] ?? 1);

        return $tasa > 0 ? $tasa : 1.0;
    }

    /** Venta gravada mínima, sin IVA, para retener o percibir (por defecto $100.00). */
    public function ivaAjusteMinimoCents(): int
    {
        return (int) round((float) ($this->raw['dte_iva_ajuste_minimo'] ?? 100) * 100);
    }

    /** Usuario de la API del MH: el NIT del emisor, salvo que se haya indicado otro. */
    public function mhUser(): string
    {
        return self::digits($this->raw['dte_mh_user'] ?? '') ?: $this->nit();
    }

    public function mhPassword(): string
    {
        return $this->decryptCredential((string) ($this->raw['dte_mh_password'] ?? ''));
    }

    public function certPath(): string
    {
        return (string) ($this->raw['dte_cert_path'] ?? '');
    }

    public function certPassword(): string
    {
        return $this->decryptCredential((string) ($this->raw['dte_cert_password'] ?? ''));
    }

    /**
     * Todo lo que falta o está mal para emitir, junto, para corregirlo de una
     * vez. Un emisor mal configurado haría que el MH rechazara cada documento.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];

        if (! preg_match('/^([0-9]{14}|[0-9]{9})$/', $this->nit())) {
            $errors[] = 'El NIT del emisor debe tener 9 o 14 dígitos.';
        }
        if (! preg_match('/^[0-9]{2,8}$/', $this->nrc())) {
            $errors[] = 'El NRC debe tener entre 2 y 8 dígitos.';
        }
        if ($this->nombre() === '') {
            $errors[] = 'Falta el nombre o razón social del emisor.';
        }
        if (SvCatalogs::actividad($this->codActividad()) === null) {
            $errors[] = 'La actividad económica no está en el catálogo CAT-019.';
        }
        if (! SvCatalogs::validDepartamento($this->departamento())) {
            $errors[] = 'El departamento no es válido (CAT-012).';
        } elseif (! SvCatalogs::validMunicipio($this->departamento(), $this->municipio())) {
            $errors[] = 'El municipio no pertenece al departamento (CAT-013).';
        } elseif (! SvCatalogs::validDistrito($this->departamento(), $this->municipio(), $this->distrito())) {
            $errors[] = 'El distrito no pertenece al municipio (CAT-008).';
        }
        if ($this->direccion() === '') {
            $errors[] = 'Falta la dirección del establecimiento.';
        }
        if (mb_strlen($this->telefono()) < 8) {
            $errors[] = 'El teléfono del emisor debe tener al menos 8 caracteres.';
        }
        if (mb_strlen($this->correo()) < 6 || ! filter_var($this->correo(), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo del emisor no es válido.';
        }
        if (! SvCatalogs::validTipoEstablecimiento($this->tipoEstablecimiento())) {
            $errors[] = 'El tipo de establecimiento no es válido (CAT-009).';
        }
        if ($this->codEstable() === '') {
            $errors[] = 'El código de establecimiento debe tener 3 dígitos.';
        }
        if ($this->codPuntoVenta() === '') {
            $errors[] = 'El código de punto de venta debe tener 3 dígitos.';
        }
        foreach (['dte_cod_estable_mh' => 'establecimiento', 'dte_cod_punto_venta_mh' => 'punto de venta'] as $key => $que) {
            $mh = trim((string) ($this->raw[$key] ?? ''));
            if ($mh !== '' && strlen($mh) !== 4) {
                $errors[] = "El código de {$que} asignado por el MH debe tener 4 caracteres.";
            }
        }
        // Lo exige el evento de contingencia, que puede hacer falta en cualquier momento.
        if ($this->responsableNombre() === '' || ! SvCatalogs::validTipoDocumento($this->responsableTipoDoc())
            || $this->responsableNumDoc() === '') {
            $errors[] = 'Faltan el nombre, el tipo o el número de documento del responsable del establecimiento.';
        }

        return $errors;
    }

    /** Lo que falta para poder firmar y transmitir, además del emisor. */
    public function transmissionErrors(): array
    {
        $errors = [];
        if ($this->mhPassword() === '') {
            $errors[] = 'Falta la contraseña de la API del Ministerio de Hacienda.';
        }
        if ($this->certPath() === '') {
            $errors[] = 'Falta cargar el certificado de firma.';
        }

        return $errors;
    }

    public static function digits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private static function threeDigits(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '001';
        }
        if (preg_match('/^[MSBP]?([0-9]{3})$/', $value, $m)) {
            return $m[1];
        }

        return '';
    }
}
