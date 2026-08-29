<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\EncryptsCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use EncryptsCredentials;

    /** Campos que se almacenan cifrados en la DB. */
    private const CREDENTIAL_KEYS = [
        'paypal_client_id', 'paypal_client_secret',
        'stripe_public_key', 'stripe_secret_key',
        'wompi_public_key', 'wompi_private_key', 'wompi_audience',
        'dte_mh_password', 'dte_cert_password',
    ];

    /**
     * Secretos que NUNCA salen de la API en claro, ni siquiera para un admin: el
     * frontend los cacheaba en localStorage, así que basta con un XSS o un equipo
     * compartido para llevarse las llaves de las pasarelas. Se devuelven
     * enmascarados y solo se escriben cuando llega un valor nuevo.
     */
    private const SECRET_KEYS = [
        'paypal_client_secret',
        'stripe_secret_key',
        'wompi_private_key',
        'dte_mh_password',
        'dte_cert_password',
    ];

    /** Marca que identifica un valor enmascarado devuelto por la API. */
    private const MASK = '••••••••';

    /** Extensiones que puede tener un logo/imagen del sitio en disco. */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    // Public subset of keys exposed without auth
    private const PUBLIC_KEYS = [
        'app',
        'social_auth_services',
        // El checkout necesita saber qué métodos de pago se ofrecen. Sin esta
        // fila, un cliente no recibía ninguna clave payment_* y el paso de pago
        // quedaba vacío para todo el mundo salvo los administradores.
        'payment_gateway',
    ];

    /**
     * Únicas claves de `payment_gateway` visibles sin permisos de administración:
     * qué métodos están activos y los datos que el cliente necesita para pagar.
     * Las credenciales de las pasarelas nunca entran aquí.
     */
    private const PUBLIC_PAYMENT_KEYS = [
        'payment_cash_enabled',
        'payment_bank_transfer_enabled',
        'payment_paypal_enabled',
        'payment_stripe_enabled',
        'payment_wompi_enabled',
        'payment_whatsapp_enabled',
        'payment_bank_name',
        'payment_bank_account',
        'payment_bank_routing',
        'payment_bank_swift',
        'payment_whatsapp_number',
        'default_currency',
        // Estructura heredada del seeder: más abajo se aplana exponiendo solo
        // los flags y el modo; las credenciales siguen siendo de admin.
        'payment_methods',
    ];

    // All keys the settings API manages
    private const ALL_KEYS = [
        'app',
        'payment_gateway',
        'social_auth_services',
        'taxes',
        'shipping',
        'mail',
        'dte',
    ];

    /**
     * GET /api/v1/settings
     * Returns a flat merged attributes object from all setting rows.
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = (bool) optional($request->user())->can('settings:update');
        $keys = $isAdmin ? self::ALL_KEYS : self::PUBLIC_KEYS;

        $settings = Setting::whereIn('key', $keys)->get()
            ->keyBy('key')
            ->map(fn ($s) => json_decode($s->value, true));

        $flat = $this->flatten($settings->toArray(), $isAdmin);

        return response()->json([
            'data' => [
                'type' => 'settings',
                'id' => 'current',
                'attributes' => $flat,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/settings
     * Accepts a flat attributes object and merges into the correct DB rows.
     */
    public function update(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', []);

        // Load all current rows
        $rows = Setting::whereIn('key', self::ALL_KEYS)->get()->keyBy('key');

        $paymentGateway = json_decode(optional($rows->get('payment_gateway'))->value ?? '{}', true) ?? [];
        $app = json_decode(optional($rows->get('app'))->value ?? '{}', true) ?? [];
        $socialAuth = json_decode(optional($rows->get('social_auth_services'))->value ?? '{}', true) ?? [];

        // ── payment_gateway fields ─────────────────────────────────────────
        $pmFields = [
            'payment_cash_enabled', 'payment_bank_transfer_enabled',
            'payment_bank_name', 'payment_bank_account', 'payment_bank_routing', 'payment_bank_swift',
            // enabled flags per gateway
            'payment_paypal_enabled', 'payment_stripe_enabled', 'payment_wompi_enabled',
            // Pago asistido por WhatsApp: un agente acompaña al cliente. El número
            // es opcional; si se deja vacío se usa el de contacto del sitio.
            'payment_whatsapp_enabled', 'payment_whatsapp_number',
            // credentials
            'paypal_mode', 'paypal_client_id', 'paypal_client_secret',
            'stripe_mode', 'stripe_public_key', 'stripe_secret_key',
            'wompi_mode', 'wompi_public_key', 'wompi_private_key', 'wompi_audience',
            'default_currency',
        ];
        foreach ($pmFields as $f) {
            if (array_key_exists($f, $attrs) && ! $this->isMaskedValue($f, $attrs[$f])) {
                $paymentGateway[$f] = in_array($f, self::CREDENTIAL_KEYS, true)
                    ? $this->encryptCredential((string) $attrs[$f])
                    : $attrs[$f];
            }
        }

        // ── app fields ─────────────────────────────────────────────────────
        $appFields = ['app_name', 'app_tagline', 'app_logo_url', 'app_logo_dark_url',
            'contact_email', 'inquiry_notification_emails', 'contact_phone', 'contact_whatsapp', 'contact_address',
            'contact_city', 'contact_country',
            'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube', 'social_tiktok',
            'timezone',
            'max_daily_bookings',
            'offers_subscription_enabled',
            // Sección "Nosotros" (imagen, textos, guías y estadísticas). Si se
            // dejan vacíos, el frontend usa los valores por defecto (i18n).
            'about_team_image_url', 'about_eyebrow', 'about_title', 'about_p1', 'about_p2', 'about_cta',
            'about_guides_count', 'about_guides_label', 'about_guides_national',
            'about_stats',
            // Política de reserva GLOBAL (aplica a todos los tours y vehículos).
            'booking_min_advance_days',
            'booking_cancellation_hours',
        ];
        foreach ($appFields as $f) {
            if (array_key_exists($f, $attrs)) {
                $app[$f] = $attrs[$f];
            }
        }

        // ── DTE (Facturación Electrónica El Salvador) ─────────────────────
        $dte = json_decode(optional($rows->get('dte'))->value ?? '{}', true) ?? [];

        $dteFields = [
            'dte_enabled', 'dte_environment',
            'dte_nit', 'dte_nrc',
            'dte_nombre', 'dte_nombre_comercial',
            'dte_cod_actividad', 'dte_desc_actividad',
            'dte_departamento', 'dte_municipio', 'dte_direccion',
            'dte_telefono', 'dte_correo',
            'dte_cod_establec', 'dte_cod_punto_venta',
            'dte_mh_user', 'dte_mh_password',
            'dte_cert_path', 'dte_cert_password',
            'dte_auto_generate',
        ];
        foreach ($dteFields as $f) {
            if (array_key_exists($f, $attrs) && ! $this->isMaskedValue($f, $attrs[$f])) {
                $dte[$f] = in_array($f, self::CREDENTIAL_KEYS, true)
                    ? $this->encryptCredential((string) $attrs[$f])
                    : $attrs[$f];
            }
        }
        Setting::updateOrCreate(['key' => 'dte'], ['value' => json_encode($dte)]);

        // ── social_auth_services ───────────────────────────────────────────
        $socialFields = [
            'google_login_enabled', 'google_client_id',
            'facebook_login_enabled', 'facebook_app_id',
        ];
        foreach ($socialFields as $f) {
            if (array_key_exists($f, $attrs)) {
                $socialAuth[$f] = $attrs[$f];
            }
        }

        // Persist
        Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode($paymentGateway)]);
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode($app)]);
        Setting::updateOrCreate(['key' => 'social_auth_services'], ['value' => json_encode($socialAuth)]);

        return $this->index($request);
    }

    /**
     * POST /api/v1/settings/logo
     * Upload or replace an application logo image.
     *
     * `variant` selects which logo:
     *   - 'light' (default) → logo claro/blanco para fondos oscuros → app_logo_url
     *   - 'dark'            → logo oscuro para fondos claros        → app_logo_dark_url
     *
     * Stores the file on the public disk and saves the URL in app settings.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpeg,png,webp'],
            'variant' => ['sometimes', 'in:light,dark'],
        ]);

        $variant = $request->input('variant', 'light');
        $basename = $variant === 'dark' ? 'logo-dark' : 'logo';
        $attribute = $variant === 'dark' ? 'app_logo_dark_url' : 'app_logo_url';

        $file = $request->file('image');
        // Extensión deducida del contenido real, no del nombre que envía el cliente:
        // el nombre es la vía por la que se cuela un .phtml en el document root.
        $extension = $this->safeExtension($file);
        $path = "settings/{$basename}.{$extension}";

        // Delete any existing file of this variant with any extension
        foreach (self::IMAGE_EXTENSIONS as $ext) {
            Storage::disk('public')->delete("settings/{$basename}.{$ext}");
        }

        Storage::disk('public')->putFileAs('settings', $file, "{$basename}.{$extension}");
        $logoUrl = Storage::disk('public')->url($path);

        // Persist in the 'app' settings row
        $row = Setting::where('key', 'app')->first();
        $app = json_decode($row?->value ?? '{}', true) ?? [];
        $app[$attribute] = $logoUrl;
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode($app)]);

        return response()->json([
            'data' => [
                'type' => 'logo',
                'attributes' => [$attribute => $logoUrl],
            ],
        ]);
    }

    /**
     * POST /api/v1/settings/about-image
     * Upload or replace the "Nosotros" (team) section image.
     */
    public function uploadAboutImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpeg,png,webp'],
        ]);

        $file = $request->file('image');
        $extension = $this->safeExtension($file);
        $path = "settings/about-team.{$extension}";

        foreach (self::IMAGE_EXTENSIONS as $ext) {
            Storage::disk('public')->delete("settings/about-team.{$ext}");
        }

        Storage::disk('public')->putFileAs('settings', $file, "about-team.{$extension}");
        $imageUrl = Storage::disk('public')->url($path);

        $row = Setting::where('key', 'app')->first();
        $app = json_decode($row?->value ?? '{}', true) ?? [];
        $app['about_team_image_url'] = $imageUrl;
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode($app)]);

        return response()->json([
            'data' => [
                'type' => 'about-image',
                'attributes' => ['about_team_image_url' => $imageUrl],
            ],
        ]);
    }

    // ── private helpers ────────────────────────────────────────────────────

    /**
     * Extensión segura para un fichero subido: se deriva del MIME real y se
     * contrasta con la lista blanca. Nunca se usa el nombre original.
     */
    private function safeExtension(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->extension());

        return in_array($extension, self::IMAGE_EXTENSIONS, true) ? $extension : 'png';
    }

    /**
     * Enmascara un secreto dejando visibles los últimos 4 caracteres, lo justo
     * para que un admin reconozca cuál tiene configurado.
     */
    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return self::MASK.substr($value, -4);
    }

    /**
     * ¿Hay que conservar el secreto ya guardado? Sí cuando el formulario devuelve
     * el valor enmascarado o un campo vacío, es decir, cuando el admin no lo tocó.
     * Para retirar una credencial se desactiva la pasarela.
     */
    private function isMaskedValue(string $field, mixed $value): bool
    {
        if (! in_array($field, self::SECRET_KEYS, true)) {
            return false;
        }

        return ! is_string($value) || $value === '' || str_contains($value, self::MASK);
    }

    /**
     * Flatten all setting rows into a single key-value map.
     * Payment credentials are only included for admins.
     */
    private function flatten(array $rows, bool $isAdmin): array
    {
        $flat = [];

        foreach ($rows as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if ($key === 'payment_gateway' && ! $isAdmin) {
                $value = array_intersect_key($value, array_flip(self::PUBLIC_PAYMENT_KEYS));
            }

            foreach ($value as $k => $v) {
                // Descifrar credenciales; fallback transparente para valores legacy sin cifrar
                if (is_string($v) && in_array($k, self::CREDENTIAL_KEYS, true)) {
                    $v = $this->decryptCredential($v);
                }

                // Los secretos salen enmascarados; junto a cada uno viaja un flag
                // para que el formulario sepa si ya hay valor guardado.
                if (in_array($k, self::SECRET_KEYS, true)) {
                    $flat[$k.'_configured'] = is_string($v) && $v !== '';
                    $v = $this->maskSecret(is_string($v) ? $v : '');
                }

                $flat[$k] = $v;
            }
        }

        // Merge nested payment_methods structure if present
        if (isset($flat['payment_methods']) && is_array($flat['payment_methods'])) {
            $pm = $flat['payment_methods'];
            unset($flat['payment_methods']);

            if (isset($pm['paypal'])) {
                $flat['payment_paypal_enabled'] = $pm['paypal']['enabled'] ?? false;
                $flat['paypal_mode'] = $pm['paypal']['mode'] ?? 'sandbox';
                if ($isAdmin) {
                    $flat['paypal_client_id'] = $pm['paypal']['client_id'] ?? '';
                    $flat['paypal_client_secret_configured'] = ($pm['paypal']['client_secret'] ?? '') !== '';
                    $flat['paypal_client_secret'] = $this->maskSecret((string) ($pm['paypal']['client_secret'] ?? ''));
                }
            }
            if (isset($pm['stripe'])) {
                $flat['payment_stripe_enabled'] = $pm['stripe']['enabled'] ?? false;
                $flat['stripe_mode'] = $pm['stripe']['mode'] ?? 'sandbox';
                if ($isAdmin) {
                    $flat['stripe_public_key'] = $pm['stripe']['key'] ?? '';
                    $flat['stripe_secret_key_configured'] = ($pm['stripe']['secret'] ?? '') !== '';
                    $flat['stripe_secret_key'] = $this->maskSecret((string) ($pm['stripe']['secret'] ?? ''));
                }
            }
            if (isset($pm['wompi'])) {
                $flat['payment_wompi_enabled'] = $pm['wompi']['enabled'] ?? false;
                $flat['wompi_mode'] = $pm['wompi']['mode'] ?? 'sandbox';
                if ($isAdmin) {
                    $flat['wompi_public_key'] = $pm['wompi']['key'] ?? '';
                    $flat['wompi_private_key_configured'] = ($pm['wompi']['secret'] ?? '') !== '';
                    $flat['wompi_private_key'] = $this->maskSecret((string) ($pm['wompi']['secret'] ?? ''));
                }
            }
        }

        // Strip credentials from non-admins
        if (! $isAdmin) {
            $sensitiveKeys = [
                'paypal_client_secret', 'stripe_secret_key', 'wompi_private_key',
                'mail', 'password',
                'inquiry_notification_emails', // destinatarios internos, no públicos
            ];
            foreach ($sensitiveKeys as $sk) {
                unset($flat[$sk]);
            }
        }

        return $flat;
    }
}
