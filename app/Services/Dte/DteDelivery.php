<?php

namespace App\Services\Dte;

use App\Jobs\DeliverDteDocument;
use App\Mail\DteDocumentMail;
use App\Models\DteDocument;
use Illuminate\Support\Facades\Mail;

/**
 * Entrega del DTE al receptor (Manual Funcional v2, IV): por correo, a la
 * dirección que declara el propio DTE, con el PDF y el archivo DTE.
 *
 * Cuándo se envía solo:
 *  - con sello: la entrega definitiva;
 *  - en contingencia: el manual pide entregarlo al generarlo, sin sello; al
 *    llegar el sello se reenvía la versión definitiva.
 *
 * Nunca en ambiente de pruebas (00): esos documentos no tienen validez y no
 * deben llegar a clientes reales. Ahí solo se envían a mano.
 */
class DteDelivery
{
    /**
     * Decide si un documento recién sellado o emitido en contingencia debe
     * salir ya hacia su receptor. Se envía después de la respuesta: quien
     * emite no espera al servidor de correo.
     */
    public static function autoDeliver(DteDocument $doc): void
    {
        if ($doc->ambiente !== DteConfig::AMBIENTE_PRODUCCION || self::correoReceptor($doc) === '') {
            return;
        }

        $toca = match ($doc->estado) {
            DteDocument::TRANSMITTED => ! $doc->entregado_con_sello,
            DteDocument::CONTINGENCY => $doc->entregado_at === null,
            default => false,
        };

        if ($toca) {
            DeliverDteDocument::dispatchAfterResponse($doc->id);
        }
    }

    /**
     * Envía el DTE por correo. `$to` sustituye al correo que declara el DTE
     * (un reenvío a otra dirección).
     *
     * @throws DteException
     */
    public function deliver(DteDocument $doc, ?string $to = null): DteDocument
    {
        if (! in_array($doc->estado, [DteDocument::TRANSMITTED, DteDocument::CONTINGENCY], true)) {
            throw new DteException('Solo se entrega un DTE con sello o emitido en contingencia.');
        }

        $to = trim((string) $to) ?: self::correoReceptor($doc);
        if ($to === '') {
            throw new DteException('El DTE no tiene correo del receptor: indica a qué correo enviarlo.');
        }
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new DteException('El correo no es válido.');
        }

        Mail::to($to)->send(new DteDocumentMail($doc));

        $doc->update([
            'entregado_at' => now(),
            'entregado_a' => $to,
            'entregado_con_sello' => $doc->entregado_con_sello || $doc->estado === DteDocument::TRANSMITTED,
        ]);

        return $doc;
    }

    /** El correo que declara el propio DTE. */
    public static function correoReceptor(DteDocument $doc): string
    {
        return trim((string) ($doc->document()['receptor']['correo'] ?? ''));
    }
}
