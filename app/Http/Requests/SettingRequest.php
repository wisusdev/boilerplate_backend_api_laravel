<?php

namespace App\Http\Requests;

use App\Support\InvoiceDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de los ajustes del sitio.
 *
 * Todas las reglas son `sometimes`: el panel envía solo la pestaña que se está
 * guardando, así que un PATCH parcial debe seguir funcionando. Lo que se busca
 * es que un número, una hora o un correo mal formados no se persistan y acaben
 * casteados a 0 o a cadena vacía al leerlos.
 */
class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $texto = ['sometimes', 'nullable', 'string', 'max:255'];
        $bool = ['sometimes', 'boolean'];
        $url = ['sometimes', 'nullable', 'string', 'url', 'max:500'];

        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],

            // ── Identidad y contacto ──
            'data.attributes.app_name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'data.attributes.app_tagline' => $texto,
            'data.attributes.app_logo_url' => $url,
            'data.attributes.app_logo_dark_url' => $url,
            'data.attributes.contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'data.attributes.contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'data.attributes.contact_whatsapp' => ['sometimes', 'nullable', 'string', 'max:40'],
            'data.attributes.contact_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'data.attributes.contact_city' => $texto,
            'data.attributes.contact_country' => $texto,
            // Lista de correos separada por comas.
            'data.attributes.inquiry_notification_emails' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^\s*$|^[^,@\s]+@[^,@\s]+(\s*,\s*[^,@\s]+@[^,@\s]+)*\s*$/'],

            'data.attributes.social_facebook' => $texto,
            'data.attributes.social_instagram' => $texto,
            'data.attributes.social_twitter' => $texto,
            'data.attributes.social_youtube' => $texto,
            'data.attributes.social_tiktok' => $texto,

            // ── Política de reserva: son enteros y se leen como tales ──
            'data.attributes.timezone' => ['sometimes', 'nullable', 'timezone'],
            'data.attributes.max_daily_bookings' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'data.attributes.booking_min_advance_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'data.attributes.booking_cancellation_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:8760'],
            'data.attributes.offers_subscription_enabled' => $bool,

            // ── Sección "Nosotros" ──
            'data.attributes.about_team_image_url' => $url,
            'data.attributes.about_eyebrow' => $texto,
            'data.attributes.about_title' => $texto,
            'data.attributes.about_p1' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'data.attributes.about_p2' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'data.attributes.about_cta' => $texto,
            'data.attributes.about_guides_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'data.attributes.about_guides_label' => $texto,
            'data.attributes.about_guides_national' => $texto,
            'data.attributes.about_stats' => ['sometimes', 'nullable', 'array'],

            // ── Documentos ──
            'data.attributes.invoice_template' => ['sometimes', 'string', Rule::in(array_keys(InvoiceDocument::TEMPLATES))],

            // ── Métodos de pago ──
            // PayPal, Stripe y transferencia bancaria se retiraron del producto
            // (ver PAGO-ENLACE-BAC.md): quedan efectivo, WhatsApp asistido,
            // Wompi y el enlace de pago del banco.
            'data.attributes.payment_cash_enabled' => $bool,
            'data.attributes.payment_wompi_enabled' => $bool,
            'data.attributes.payment_whatsapp_enabled' => $bool,
            'data.attributes.payment_whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:40'],
            // Enlaces de pago del banco (BAC). La lista de dominios se teclea
            // separada por comas o saltos de línea.
            'data.attributes.payment_bac_link_enabled' => $bool,
            'data.attributes.payment_bac_link_hosts' => ['sometimes', 'nullable', 'string', 'max:500'],
            'data.attributes.payment_bac_link_ttl_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:8760'],
            'data.attributes.payment_bac_dual_control' => $bool,
            'data.attributes.payment_bac_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'data.attributes.payment_bac_link_auto_release' => $bool,
            'data.attributes.default_currency' => ['sometimes', 'nullable', 'string', 'size:3'],

            // Credenciales: el contenido lo fija cada pasarela, solo se acota
            // el tipo y la longitud. Un valor vacío conserva el guardado.
            'data.attributes.wompi_mode' => ['sometimes', 'nullable', Rule::in(['sandbox', 'live'])],
            'data.attributes.wompi_public_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.wompi_private_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.wompi_audience' => ['sometimes', 'nullable', 'string', 'max:255'],

            // ── Login social ──
            'data.attributes.google_login_enabled' => $bool,
            'data.attributes.google_client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.facebook_login_enabled' => $bool,
            'data.attributes.facebook_app_id' => ['sometimes', 'nullable', 'string', 'max:255'],

            // ── Facturación electrónica (DTE) ──
            'data.attributes.dte_enabled' => $bool,
            'data.attributes.dte_auto_generate' => $bool,
            // El formulario envía test/production; se aceptan también los códigos del MH.
            'data.attributes.dte_environment' => ['sometimes', 'nullable', Rule::in(['test', 'production', '00', '01'])],
            'data.attributes.dte_nit' => ['sometimes', 'nullable', 'string', 'max:25'],
            'data.attributes.dte_nrc' => ['sometimes', 'nullable', 'string', 'max:20'],
            'data.attributes.dte_nombre' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.attributes.dte_nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.attributes.dte_cod_actividad' => ['sometimes', 'nullable', 'string', 'max:10'],
            'data.attributes.dte_desc_actividad' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.attributes.dte_departamento' => ['sometimes', 'nullable', 'string', 'max:5'],
            'data.attributes.dte_municipio' => ['sometimes', 'nullable', 'string', 'max:5'],
            'data.attributes.dte_distrito' => ['sometimes', 'nullable', 'string', 'max:5'],
            'data.attributes.dte_tipo_establecimiento' => ['sometimes', 'nullable', Rule::in(['01', '02', '04', '07'])],
            'data.attributes.dte_cod_estable_mh' => ['sometimes', 'nullable', 'string', 'max:4'],
            'data.attributes.dte_cod_punto_venta_mh' => ['sometimes', 'nullable', 'string', 'max:4'],
            'data.attributes.dte_responsable_nombre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'data.attributes.dte_responsable_tipo_doc' => ['sometimes', 'nullable', Rule::in(['13', '36', '03', '02', '37'])],
            'data.attributes.dte_responsable_num_doc' => ['sometimes', 'nullable', 'string', 'max:25'],
            'data.attributes.dte_percepcion_activa' => $bool,
            'data.attributes.dte_retencion_activa' => $bool,
            'data.attributes.dte_agente_retencion' => $bool,
            'data.attributes.dte_iva_ajuste_tasa' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:13'],
            'data.attributes.dte_iva_ajuste_minimo' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'data.attributes.dte_direccion' => ['sometimes', 'nullable', 'string', 'max:500'],
            'data.attributes.dte_telefono' => ['sometimes', 'nullable', 'string', 'max:40'],
            'data.attributes.dte_correo' => ['sometimes', 'nullable', 'email', 'max:150'],
            'data.attributes.dte_cod_establec' => ['sometimes', 'nullable', 'string', 'max:10'],
            'data.attributes.dte_cod_punto_venta' => ['sometimes', 'nullable', 'string', 'max:10'],
            'data.attributes.dte_mh_user' => ['sometimes', 'nullable', 'string', 'max:100'],
            'data.attributes.dte_mh_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.dte_cert_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.dte_cert_password' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.attributes.inquiry_notification_emails.regex' => 'Escribe correos válidos separados por comas.',
            'data.attributes.timezone.timezone' => 'La zona horaria no es válida.',
        ];
    }
}
