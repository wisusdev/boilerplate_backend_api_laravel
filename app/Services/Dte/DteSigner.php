<?php

namespace App\Services\Dte;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Firma electrónica del DTE.
 *
 * El DTE firmado es un JWS compacto (RFC 7515) con algoritmo RS512 cuyo
 * payload es el JSON del documento: así lo muestra el ejemplo de archivo DTE
 * del Manual Funcional v2 (sección XXIII), con la cabecera {"alg":"RS512"}.
 * Base64 url sin relleno, no base64 estándar.
 */
class DteSigner
{
    public const CERT_PATH = 'dte/certificate';

    /** base64url({"alg":"RS512"}), byte a byte lo que emite el firmador del MH. */
    private const HEADER = 'eyJhbGciOiJSUzUxMiJ9';

    /** Firma el JSON exacto que se guarda y se envía: nunca se vuelve a serializar. */
    public function sign(string $json, OpenSSLAsymmetricKey $key): string
    {
        $input = self::HEADER.'.'.self::base64url($json);

        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA512)) {
            throw new RuntimeException('No se pudo firmar el DTE.');
        }

        return $input.'.'.self::base64url($signature);
    }

    /** Llave del certificado guardado. Se carga antes de consumir un número de control. */
    public function loadKey(DteConfig $config): OpenSSLAsymmetricKey
    {
        $path = $config->certPath();
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('No hay un certificado de firma cargado.');
        }

        $content = Storage::disk('local')->get($path);
        try {
            $content = Crypt::decryptString($content);
        } catch (DecryptException) {
            // Certificado subido antes de guardarse cifrado.
        }

        return $this->parseKey($content, $config->certPassword());
    }

    /**
     * Acepta los dos formatos en que llega un certificado:
     *  - el .crt XML que el MH emite a cada contribuyente (<CertificadoMH>): la
     *    llave privada va en <privateKey><encodied> como PKCS#8 en base64 y
     *    <clave> es el SHA-512 en hexadecimal de la contraseña. Formato tomado
     *    del firmador del MH; confirmarlo con el primer certificado real.
     *  - un PKCS#12 (.p12/.pfx) emitido por otra entidad.
     */
    public function parseKey(string $content, string $password): OpenSSLAsymmetricKey
    {
        if (str_contains($content, '<privateKey>')) {
            return $this->parseMhCertificate($content, $password);
        }

        if (! openssl_pkcs12_read($content, $certs, $password)) {
            throw new RuntimeException('El certificado no es un certificado del MH (.crt) ni un PKCS#12 válido, o la contraseña no coincide.');
        }

        $key = openssl_pkey_get_private($certs['pkey']);
        if ($key === false) {
            throw new RuntimeException('El certificado no contiene una llave privada.');
        }

        return $this->ensureRsa($key);
    }

    private function parseMhCertificate(string $xml, string $password): OpenSSLAsymmetricKey
    {
        if (! preg_match('#<privateKey>(.*?)</privateKey>#s', $xml, $block)) {
            throw new RuntimeException('Certificado del MH sin llave privada.');
        }

        if (preg_match('#<clave>\s*([0-9a-fA-F]+)\s*</clave>#s', $block[1], $clave)
            && strcasecmp($clave[1], hash('sha512', $password)) !== 0) {
            throw new RuntimeException('La contraseña de la llave privada no coincide con el certificado.');
        }

        if (! preg_match('#<encodied>\s*([A-Za-z0-9+/=\s]+?)\s*</encodied>#s', $block[1], $encoded)) {
            throw new RuntimeException('Certificado del MH sin llave privada codificada.');
        }

        $pem = "-----BEGIN PRIVATE KEY-----\n"
            .chunk_split(preg_replace('/\s+/', '', $encoded[1]), 64, "\n")
            ."-----END PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('La llave privada del certificado no es válida.');
        }

        return $this->ensureRsa($key);
    }

    private function ensureRsa(OpenSSLAsymmetricKey $key): OpenSSLAsymmetricKey
    {
        if ((openssl_pkey_get_details($key)['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('El certificado no contiene una llave privada RSA.');
        }

        return $key;
    }

    public static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
