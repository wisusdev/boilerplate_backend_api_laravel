<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginResource;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inicio de sesión con Google / Facebook.
 *
 * Regla central: no basta con que el token sea válido — debe haber sido emitido
 * PARA ESTA APLICACIÓN. Un token emitido a otra app OAuth (incluida una del
 * atacante, donde la víctima haya iniciado sesión) no puede servir aquí; de lo
 * contrario cualquiera se autentica como cualquier usuario (confused deputy).
 *
 * Segunda regla: solo se enlaza con una cuenta local existente si el proveedor
 * afirma que el correo está verificado. Eso prueba que quien presenta el token
 * controla ese buzón.
 */
class SocialAuthController extends Controller
{
    public function verifySocialToken(Request $request): JsonResource
    {
        $request->validate([
            'provider' => ['required', 'in:google,facebook'],
            'access_token' => ['required_without:id_token', 'nullable', 'string', 'max:4096'],
            'id_token' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ]);

        $provider = $request->input('provider');

        $this->assertProviderEnabled($provider);

        $profile = $provider === 'google'
            ? $this->verifyGoogle($request->input('id_token'), $request->input('access_token'))
            : $this->verifyFacebook((string) $request->input('access_token'));

        $user = $this->resolveUser($profile);

        $tokenResult = $user->createToken('Social login');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        return LoginResource::make((object) [
            'user' => $user->loadMissing('roles'),
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString(),
        ]);
    }

    // ─── Verificación por proveedor ───────────────────────────────────────────

    /**
     * Google. Se prefiere el id_token (JWT firmado); con access_token se consulta
     * el endpoint tokeninfo, que devuelve la audiencia a la que se emitió.
     *
     * @return array{email: string, name: string, provider_id: string}
     */
    private function verifyGoogle(?string $idToken, ?string $accessToken): array
    {
        $clientId = $this->providerSetting('google_client_id');

        if ($clientId === '') {
            throw ValidationException::withMessages([
                'provider' => ['validation.socialProviderNotConfigured'],
            ]);
        }

        $info = $idToken
            ? $this->getJson('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken])
            : $this->getJson('https://www.googleapis.com/oauth2/v3/tokeninfo', ['access_token' => $accessToken]);

        // `aud` es la app a la que Google emitió el token; `azp` el cliente que lo pidió.
        $audience = (string) ($info['aud'] ?? '');
        $authorizedParty = (string) ($info['azp'] ?? '');

        if (! hash_equals($clientId, $audience) && ! hash_equals($clientId, $authorizedParty)) {
            $this->rejectToken();
        }

        if ($idToken) {
            $issuer = (string) ($info['iss'] ?? '');
            if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
                $this->rejectToken();
            }
        }

        if ((int) ($info['exp'] ?? 0) > 0 && (int) $info['exp'] < time()) {
            $this->rejectToken();
        }

        $email = (string) ($info['email'] ?? '');
        $verified = filter_var($info['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || ! $verified) {
            throw ValidationException::withMessages([
                'access_token' => ['validation.socialEmailNotVerified'],
            ]);
        }

        $name = (string) ($info['name'] ?? '');

        // tokeninfo no siempre trae el nombre; se completa con userinfo.
        if ($name === '' && $accessToken) {
            $profile = $this->getJson('https://www.googleapis.com/oauth2/v3/userinfo', ['access_token' => $accessToken]);
            $name = (string) ($profile['name'] ?? '');
        }

        return [
            'email' => $email,
            'name' => $name,
            'provider_id' => (string) ($info['sub'] ?? ''),
        ];
    }

    /**
     * Facebook. `GET /app` con el token del usuario devuelve la aplicación a la
     * que pertenece ese token; si no es la nuestra, se rechaza.
     *
     * @return array{email: string, name: string, provider_id: string}
     */
    private function verifyFacebook(string $accessToken): array
    {
        $appId = $this->providerSetting('facebook_app_id');

        if ($appId === '') {
            throw ValidationException::withMessages([
                'provider' => ['validation.socialProviderNotConfigured'],
            ]);
        }

        $app = $this->getJson('https://graph.facebook.com/app', ['access_token' => $accessToken]);

        if (! hash_equals($appId, (string) ($app['id'] ?? ''))) {
            $this->rejectToken();
        }

        $data = $this->getJson('https://graph.facebook.com/me', [
            'access_token' => $accessToken,
            'fields' => 'id,name,email',
        ]);

        $email = (string) ($data['email'] ?? '');

        // Facebook solo entrega el correo cuando está confirmado en la cuenta;
        // si falta, no hay identidad con la que enlazar de forma segura.
        if ($email === '') {
            throw ValidationException::withMessages([
                'access_token' => ['validation.socialEmailNotVerified'],
            ]);
        }

        return [
            'email' => $email,
            'name' => (string) ($data['name'] ?? ''),
            'provider_id' => (string) ($data['id'] ?? ''),
        ];
    }

    // ─── Usuario local ────────────────────────────────────────────────────────

    /**
     * @param  array{email: string, name: string, provider_id: string}  $profile
     */
    private function resolveUser(array $profile): User
    {
        $existing = User::where('email', $profile['email'])->first();

        if ($existing) {
            // El proveedor ya confirmó que quien presenta el token controla este
            // buzón, así que enlazar con la cuenta local es seguro.
            if (! $existing->hasVerifiedEmail()) {
                $existing->markEmailAsVerified();
            }

            return $existing;
        }

        $parts = preg_split('/\s+/', trim($profile['name']) ?: 'Usuario', 2);

        $user = User::create([
            'username' => $this->uniqueUsername($profile['email']),
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? '',
            'email' => $profile['email'],
            // Sin contraseña utilizable: la cuenta se usa vía proveedor social
            // (o restableciendo la contraseña por correo).
            'password' => Str::random(64),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('user');

        return $user;
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', Str::before($email, '@'))) ?: 'usuario';
        $username = $base;

        while (User::where('username', $username)->exists()) {
            $username = $base.random_int(100, 9999);
        }

        return $username;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url, array $query): array
    {
        $response = Http::timeout(10)->get($url, $query);

        if (! $response->successful()) {
            $this->rejectToken();
        }

        return $response->json() ?? [];
    }

    private function rejectToken(): never
    {
        throw ValidationException::withMessages([
            'access_token' => ['validation.socialTokenInvalid'],
        ]);
    }

    private function assertProviderEnabled(string $provider): void
    {
        $enabled = $this->providerSetting($provider === 'google' ? 'google_login_enabled' : 'facebook_login_enabled');

        if (! filter_var($enabled ?: false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'provider' => ['validation.socialProviderDisabled'],
            ]);
        }
    }

    private function providerSetting(string $key): string
    {
        $row = Setting::where('key', 'social_auth_services')->first();
        $config = $row ? (json_decode($row->value, true) ?? []) : [];

        $value = $config[$key] ?? config('services.'.$key, '');

        return is_bool($value) ? ($value ? '1' : '') : trim((string) $value);
    }
}
