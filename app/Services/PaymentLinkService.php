<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\User;
use App\Notifications\BookingNotification;
use App\Notifications\PaymentLinkIssuedNotification;
use App\Support\AdminAlerts;
use App\Support\SiteSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cobro con enlaces de pago emitidos a mano en el portal del banco.
 *
 * El problema de fondo: sin API del banco, el dinero entra en un sistema que no
 * podemos leer. Nadie nos avisa. Por eso la confirmación es un acto humano y
 * todo aquí gira alrededor de hacerlo trazable y difícil de equivocar.
 *
 * Es el ÚNICO sitio que escribe a la vez en `payments` y en `payment_links`.
 * Repartir esa escritura es la forma segura de que los dos estados acaben
 * contándose historias distintas.
 *
 * @see PAGO-ENLACE-BAC.md
 */
class PaymentLinkService
{
    public const GATEWAY = 'bac_link';

    /** Tolerancia al comparar importes en decimal(12,2). */
    private const EPSILON = 0.009;

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Emite (o recupera) el enlace pendiente de una reserva.
     *
     * Idempotente a propósito: un cliente que pulsa "pagar" tres veces no debe
     * dejar tres enlaces vivos que después nadie sepa cuál cotejar.
     */
    public function issueFor(Booking $booking, ?User $actor = null): PaymentLink
    {
        return DB::transaction(function () use ($booking, $actor): PaymentLink {
            $abierto = $this->openLinkFor($booking);

            if ($abierto) {
                return $abierto;
            }

            $importe = $this->payments->outstandingFor($booking);

            if ($importe <= 0) {
                throw ValidationException::withMessages([
                    'data.attributes.payable_id' => ['message.bookingAlreadyPaid'],
                ]);
            }

            $moneda = $booking->currency_code ?: SiteSettings::currency();

            $payment = $this->payments->create($booking, [
                'gateway' => self::GATEWAY,
                'method' => 'card',
                'amount' => $importe,
                'currency_code' => $moneda,
                'status' => 'pending',
            ]);

            $ttl = SiteSettings::bacLinkTtlHours();

            $link = PaymentLink::create([
                'payment_id' => $payment->id,
                'provider' => 'bac',
                'reference' => $this->generateReference($booking),
                'status' => PaymentLink::STATUS_DRAFT,
                'amount' => $importe,
                'currency_code' => $moneda,
                'expires_at' => $ttl > 0 ? now()->addHours($ttl) : null,
                'created_by' => $actor?->getKey(),
            ]);

            // El enlace no se genera solo: alguien tiene que entrar al portal del
            // banco. El aviso va aquí y no en el controlador para que salga una
            // sola vez, aunque el cliente pulse "pagar" varias veces.
            $booking->loadMissing(['bookable', 'user']);

            AdminAlerts::send(
                'Hay que emitir un enlace de pago',
                'Genera el enlace en el portal del banco y pégalo en el panel para que le llegue al cliente.',
                array_filter([
                    'referencia' => $link->reference,
                    'reserva' => '#'.$booking->id,
                    'detalle' => $booking->bookable?->title,
                    'importe' => $this->money($importe, $moneda),
                    'cliente' => trim((string) $booking->user?->name).' · '.(string) $booking->user?->email,
                    'fecha del servicio' => $booking->starts_at?->format('d/m/Y H:i'),
                ])
            );

            return $link;
        });
    }

    /**
     * El agente pega la URL generada en el portal del banco.
     *
     * La validación del dominio la hace `BankPaymentLinkUrl` en el FormRequest;
     * aquí solo se registra quién la puso, porque enviar un enlace a un cliente
     * en nombre del negocio tiene que tener un responsable con nombre.
     */
    public function attachUrl(PaymentLink $link, string $url, ?CarbonInterface $expiresAt, User $actor): PaymentLink
    {
        if (! $link->isOpen()) {
            throw ValidationException::withMessages([
                'data.attributes.url' => ['Este enlace ya está cerrado y no admite cambios.'],
            ]);
        }

        $link->update([
            'url' => trim($url),
            'status' => PaymentLink::STATUS_ACTIVE,
            'expires_at' => $expiresAt ?? $link->expires_at,
            // Quien pega la URL es el emisor a efectos de doble control, aunque
            // otra persona hubiera creado el borrador desde el flujo del cliente.
            'created_by' => $actor->getKey(),
        ]);

        return $this->send($link->refresh());
    }

    /**
     * Envía el enlace al cliente. Se puede repetir (el cliente perdió el correo).
     */
    public function send(PaymentLink $link): PaymentLink
    {
        if ($link->url === null) {
            throw ValidationException::withMessages([
                'link' => ['Todavía no hay un enlace que enviar.'],
            ]);
        }

        $booking = $link->booking();
        $cliente = $booking?->user;

        if (! $cliente) {
            return $link;
        }

        try {
            $cliente->notify(new PaymentLinkIssuedNotification($link, $booking));
            $link->update(['sent_at' => now(), 'sent_channel' => 'email']);
        } catch (\Throwable $e) {
            // El enlace ya existe y es válido; que falle el correo no debe
            // deshacerlo. El agente siempre puede reenviarlo o pasarlo por chat.
            Log::warning('No se pudo enviar el enlace de pago al cliente.', [
                'payment_link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $link->refresh();
    }

    /**
     * El cliente declara que ya pagó (Fase 2).
     *
     * NO mueve dinero, y esa es toda la gracia: convierte una espera ciega —en la
     * que solo nos enteramos si a alguien se le ocurre entrar al portal— en un
     * elemento de cola con evidencia adjunta.
     */
    public function report(PaymentLink $link, ?string $reference = null, ?UploadedFile $proof = null): PaymentLink
    {
        if ($link->status === PaymentLink::STATUS_CONFIRMED) {
            return $link;
        }

        if ($link->status === PaymentLink::STATUS_VOID) {
            throw ValidationException::withMessages([
                'link' => ['Este enlace fue anulado. Contacta con nosotros para retomar el pago.'],
            ]);
        }

        $link->update([
            'status' => PaymentLink::STATUS_REPORTED,
            'reported_at' => now(),
            'reported_ref' => $reference !== null && trim($reference) !== '' ? trim($reference) : $link->reported_ref,
        ]);

        if ($proof) {
            $link->addMedia($proof->getRealPath())
                ->usingFileName($proof->hashName())
                ->toMediaCollection('payment_proof');
        }

        $booking = $link->booking();

        AdminAlerts::send(
            'Un cliente reporta un pago pendiente de confirmar',
            'Hay que cotejarlo en el portal del banco y confirmarlo en el panel.',
            array_filter([
                'referencia' => $link->reference,
                'reserva' => $booking ? '#'.$booking->id : null,
                'importe' => $this->money($link->amount, $link->currency_code),
                'autorización que indica el cliente' => $link->reported_ref ?: '(no la indicó)',
                'comprobante adjunto' => $proof ? 'sí' : 'no',
                'cliente' => $booking?->user?->email,
            ])
        );

        return $link->refresh();
    }

    /**
     * El agente coteja el cobro en el portal del banco y lo da por bueno.
     *
     * Es la única operación que mueve dinero, así que concentra las guardas:
     * doble control, unicidad de la autorización, cuadre del importe y fecha
     * real del banco.
     *
     * @param  array{authorization: string, charged: float, paid_at: CarbonInterface, note?: string|null}  $datos
     */
    public function confirm(PaymentLink $link, array $datos, User $actor): PaymentLink
    {
        return DB::transaction(function () use ($link, $datos, $actor): PaymentLink {
            /** @var PaymentLink $link */
            $link = PaymentLink::query()->lockForUpdate()->findOrFail($link->id);
            $payment = $link->payment;

            // Idempotencia: dos agentes mirando la misma cola no cobran dos veces.
            if ($payment->status === 'paid' && ! $payment->isVoided()) {
                return $link;
            }

            if ($link->status === PaymentLink::STATUS_VOID) {
                throw ValidationException::withMessages([
                    'link' => ['Este enlace fue anulado; no se puede confirmar un cobro sobre él.'],
                ]);
            }

            // Un enlace caducado SÍ se puede confirmar: no podemos anularlo en el
            // banco, así que un cobro tardío es un escenario real y el dinero ya
            // está en la cuenta. Ver §6 de PAGO-ENLACE-BAC.md.

            if (SiteSettings::bacDualControl() && $link->created_by !== null && $link->created_by === $actor->getKey()) {
                throw ValidationException::withMessages([
                    'link' => ['Con el doble control activo, quien emite el enlace no puede confirmarlo.'],
                ]);
            }

            $autorizacion = trim($datos['authorization']);

            // La equivocación más probable del turno de noche es confirmar la
            // reserva A con el comprobante de la B. Aquí se corta.
            $repetida = Payment::query()
                ->where('gateway', self::GATEWAY)
                ->where('transaction_reference', $autorizacion)
                ->whereNull('voided_at')
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($repetida) {
                throw ValidationException::withMessages([
                    'data.attributes.authorization' => [
                        'Esa autorización ya se usó para confirmar otro cobro. Revísala antes de continuar.',
                    ],
                ]);
            }

            $cobrado = round((float) $datos['charged'], 2);
            $esperado = round((float) $payment->amount, 2);

            if ($cobrado <= 0) {
                throw ValidationException::withMessages([
                    'data.attributes.charged' => ['El importe cobrado debe ser mayor que cero.'],
                ]);
            }

            // Cobrar de más nunca es un cuadre: o es el enlace equivocado o es un
            // error de tecleo. No se acepta en silencio.
            if ($cobrado - $esperado > self::EPSILON) {
                throw ValidationException::withMessages([
                    'data.attributes.charged' => [
                        'El importe cobrado ('.$this->money($cobrado, $payment->currency_code).') supera al del enlace ('
                        .$this->money($esperado, $payment->currency_code).').',
                    ],
                ]);
            }

            // Cobro parcial: el pago pasa a valer lo realmente cobrado y el resto
            // vuelve a aparecer como saldo pendiente de la reserva.
            $parcial = $esperado - $cobrado > self::EPSILON;

            if ($parcial) {
                $payment->update(['amount' => $cobrado]);
            }

            $this->payments->markPaid(
                $payment,
                $autorizacion,
                [
                    'provider' => $link->provider,
                    'reference' => $link->reference,
                    'reported_ref' => $link->reported_ref,
                    'partial' => $parcial,
                ],
                $datos['paid_at'],
                [
                    'confirmed_by' => $actor->getKey(),
                    'confirmation_note' => $datos['note'] ?? null,
                ],
            );

            $link->update([
                'status' => PaymentLink::STATUS_CONFIRMED,
                'amount' => $cobrado,
            ]);

            $this->confirmBookingIfFullyPaid($link->booking());

            return $link->refresh();
        });
    }

    /**
     * Anula un enlace o revierte un cobro confirmado por error.
     *
     * Nunca borra: la fila se conserva y el saldo vuelve a estar pendiente.
     */
    public function void(PaymentLink $link, string $reason, User $actor): PaymentLink
    {
        return DB::transaction(function () use ($link, $reason, $actor): PaymentLink {
            $payment = $link->payment;

            if ($payment->status === 'paid') {
                $this->payments->voidPayment($payment, $reason, $actor);
            } else {
                $payment->update(['status' => 'failed']);
            }

            $link->update(['status' => PaymentLink::STATUS_VOID]);

            return $link->refresh();
        });
    }

    /**
     * Enlace todavía vivo de una reserva, si lo hay.
     */
    public function openLinkFor(Booking $booking): ?PaymentLink
    {
        return PaymentLink::query()
            ->whereIn('status', PaymentLink::OPEN_STATUSES)
            ->whereHas('payment', function ($q) use ($booking) {
                $q->where('payable_type', $booking::class)
                    ->where('payable_id', $booking->getKey())
                    ->where('status', 'pending');
            })
            ->latest('id')
            ->first();
    }

    /**
     * Con la reserva pagada del todo, se confirma sola.
     *
     * Nota: el webhook de Wompi NO hace esto todavía; una
     * reserva pagada con tarjeta se queda en 'pending' hasta que alguien la
     * mueve a mano. Aquí sí, porque el cobro asistido ya exige un humano y
     * dejarlo a medias sería pedirle dos pasos para una sola decisión.
     */
    private function confirmBookingIfFullyPaid(?Booking $booking): void
    {
        if (! $booking) {
            return;
        }

        // El caso que la Fase 3 hizo posible: el enlace caducó sin uso, la
        // reserva se canceló para liberar el asiento (§6 de PAGO-ENLACE-BAC.md),
        // y AHORA llega el cobro. El dinero es real, pero reactivar la reserva a
        // ciegas podría estar chocando con un asiento que ya se vendió a otro
        // cliente. Se avisa fuerte y se deja la decisión a una persona: no se
        // toca el estado de la reserva ni para bien ni para mal.
        if ($booking->status === Booking::STATUS_CANCELLED) {
            $this->alertPaidAfterCancellation($booking);

            return;
        }

        if ($booking->status !== Booking::STATUS_PENDING) {
            return;
        }

        if (! $this->payments->isFullyPaid($booking->refresh())) {
            return;
        }

        // El observador de Booking se encarga de la factura, el DTE y el aviso.
        $booking->update(['status' => Booking::STATUS_CONFIRMED]);
    }

    private function alertPaidAfterCancellation(Booking $booking): void
    {
        AdminAlerts::send(
            '⚠ Cobro confirmado sobre una reserva ya cancelada',
            'Un enlace de pago se confirmó después de que la reserva se cancelara '
            .'(probablemente por caducidad sin uso). El dinero es real, pero antes de '
            .'reactivar la reserva hay que comprobar que el cupo siga disponible: pudo '
            .'haberse vuelto a vender.',
            array_filter([
                'reserva' => '#'.$booking->id,
                'detalle' => $booking->bookable?->title,
                'fecha del servicio' => $booking->starts_at?->format('d/m/Y H:i'),
                'cliente' => $booking->user?->email,
            ])
        );
    }

    /**
     * Datos para el resumen diario: cuántos enlaces hay por estado, cuáles
     * esperan que alguien revise lo que dijo el cliente, y cuáles vencen pronto
     * (para poder avisar al cliente antes de perder la venta, no después).
     *
     * @return array{
     *   counts: array<string, int>,
     *   reported: array<int, array<string, mixed>>,
     *   expiring_soon: array<int, array<string, mixed>>,
     * }
     */
    public function digestData(): array
    {
        $abiertos = PaymentLink::query()
            ->whereIn('status', PaymentLink::OPEN_STATUSES)
            ->with(['payment.payable.bookable', 'payment.payable.user'])
            ->get();

        $conteos = $abiertos->countBy('status')->all();

        $fila = function (PaymentLink $link): array {
            $booking = $link->booking();

            return [
                'reference' => $link->reference,
                'booking_id' => $booking?->id,
                'amount' => $link->amount,
                'currency_code' => $link->currency_code,
                'customer_email' => $booking?->user?->email,
                'reported_ref' => $link->reported_ref,
            ];
        };

        $reportados = $abiertos->where('status', PaymentLink::STATUS_REPORTED)
            ->sortBy('reported_at')->map($fila)->values()->all();

        $porVencer = $abiertos->whereIn('status', [PaymentLink::STATUS_DRAFT, PaymentLink::STATUS_ACTIVE])
            ->filter(fn (PaymentLink $l) => $l->expires_at && $l->expires_at->isFuture() && $l->expires_at->diffInHours(now()) < 24)
            ->sortBy('expires_at')->map($fila)->values()->all();

        return ['counts' => $conteos, 'reported' => $reportados, 'expiring_soon' => $porVencer];
    }

    /**
     * Enlaces que caducaron sin que nadie los use: dejamos de esperar el pago.
     *
     * "Caducar" NO significa que el enlace ya no cobre — no podemos anularlo en
     * el banco, así que un cobro tardío sigue siendo posible (`confirm()` lo
     * admite igual). Significa que el sistema deja de contar con ese dinero.
     *
     * Nunca toca un enlace en `reported`: el cliente ya dijo que pagó y esa
     * evidencia necesita que una persona la revise, no que el reloj decida.
     *
     * Cuando `payment_bac_link_auto_release` está activo, además libera el
     * asiento cancelando la reserva — pero SOLO si este es el único intento de
     * pago que tiene la reserva. Si hay otro pago en curso (efectivo, WhatsApp,
     * otro enlace), tocar la reserva por un enlace abandonado sería cancelar una
     * venta que el cliente pretende cerrar por otra vía.
     *
     * @return array{expired: array<int, array<string, mixed>>, released: int}
     */
    public function expireDueLinks(): array
    {
        $autoLibera = SiteSettings::bacLinkAutoRelease();
        $vencidos = PaymentLink::query()
            ->whereIn('status', [PaymentLink::STATUS_DRAFT, PaymentLink::STATUS_ACTIVE])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['payment.payable.bookable', 'payment.payable.user'])
            ->get();

        $resumen = [];
        $liberadas = 0;

        foreach ($vencidos as $link) {
            DB::transaction(function () use ($link, $autoLibera, &$resumen, &$liberadas) {
                /** @var PaymentLink $link */
                $link = PaymentLink::query()->lockForUpdate()->find($link->id);

                if (! $link || ! in_array($link->status, [PaymentLink::STATUS_DRAFT, PaymentLink::STATUS_ACTIVE], true)) {
                    return; // Ya lo movió otra ejecución solapada.
                }

                $payment = $link->payment;
                $link->update(['status' => PaymentLink::STATUS_EXPIRED]);

                if ($payment && $payment->status === 'pending') {
                    $this->payments->markFailed($payment);
                }

                $booking = $link->booking();
                $liberoAsiento = false;

                if ($autoLibera && $booking && $booking->status === Booking::STATUS_PENDING
                    && Payment::query()->where('payable_type', $booking::class)
                        ->where('payable_id', $booking->getKey())->count() === 1) {
                    $this->releaseAbandonedBooking($booking);
                    $liberoAsiento = true;
                    $liberadas++;
                }

                $resumen[] = [
                    'reference' => $link->reference,
                    'booking_id' => $booking?->id,
                    'booking_title' => $booking?->bookable?->title,
                    'customer_email' => $booking?->user?->email,
                    'amount' => $link->amount,
                    'currency_code' => $link->currency_code,
                    'seat_released' => $liberoAsiento,
                ];
            });
        }

        if ($resumen !== []) {
            $this->alertLinksExpired($resumen, $liberadas);
        }

        return ['expired' => $resumen, 'released' => $liberadas];
    }

    /**
     * Un correo por ejecución (no uno por enlace): un turno con varios enlaces
     * vencidos a la vez no debe llenar la bandeja del back-office.
     */
    private function alertLinksExpired(array $resumen, int $liberadas): void
    {
        $detalles = [];
        foreach ($resumen as $fila) {
            $detalles[$fila['reference']] = 'reserva #'.($fila['booking_id'] ?? '?')
                .' · '.$this->money($fila['amount'], $fila['currency_code'])
                .($fila['seat_released'] ? ' · asiento liberado' : ' · reserva sin tocar (revisar)');
        }

        AdminAlerts::send(
            count($resumen).' enlace(s) de pago caducado(s) sin usar',
            $liberadas > 0
                ? "Se liberaron {$liberadas} reserva(s). El enlace sigue aceptando un pago tardío si el cliente insiste: "
                    .'confírmalo igual y revisa disponibilidad antes de reactivarla.'
                : 'Ninguna reserva se tocó automáticamente (revisión manual activada o hay otros pagos en curso).',
            $detalles
        );
    }

    /**
     * Cancela una reserva cuyo único intento de pago quedó abandonado, y avisa
     * al cliente. No es silencioso a propósito: cancelar una reserva en firme
     * sin decírselo a quien la hizo es exactamente el tipo de sorpresa que este
     * proyecto se propuso evitar (ver feedback sobre mensajes que asustan).
     */
    private function releaseAbandonedBooking(Booking $booking): void
    {
        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        $titulo = $booking->bookable?->title ?? 'tu reserva';

        $booking->user?->notify(new BookingNotification(
            'Liberamos tu reserva por falta de pago',
            "No completaste el pago de \"{$titulo}\" dentro del plazo del enlace, así que liberamos "
            .'el cupo para otros clientes. Si todavía quieres viajar, puedes reservar de nuevo cuando '
            .'quieras — y si ya pagaste, escríbenos con tu comprobante y lo revisamos enseguida.',
            ['reserva' => '#'.$booking->id]
        ));
    }

    /**
     * Referencia del comercio: lo que el agente teclea en la descripción del
     * enlace en el portal para poder cuadrarlo después contra el extracto.
     *
     * Las iniciales salen del nombre del sitio para que renombrar el negocio no
     * deje referencias con la marca antigua.
     */
    private function generateReference(Booking $booking): string
    {
        $iniciales = collect(preg_split('/\s+/', SiteSettings::name(), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($palabra) => strtoupper(preg_replace('/[^A-Za-z]/', '', $palabra) ?: ''))
            ->filter()
            ->take(2)
            ->map(fn ($palabra) => $palabra[0])
            ->implode('');

        $prefijo = $iniciales !== '' ? $iniciales : 'CG';

        // Sin caracteres ambiguos: alguien va a copiar esto a mano desde una
        // pantalla al portal del banco.
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $sufijo = '';
            for ($i = 0; $i < 4; $i++) {
                $sufijo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $referencia = sprintf('%s-%06d-%s', $prefijo, $booking->getKey(), $sufijo);
        } while (PaymentLink::query()->where('reference', $referencia)->exists());

        return $referencia;
    }

    private function money(mixed $amount, ?string $currency): string
    {
        return number_format((float) $amount, 2, '.', ',').' '.($currency ?: SiteSettings::currency());
    }
}
