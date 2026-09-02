<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentLinkAttachRequest;
use App\Http\Requests\PaymentLinkConfirmRequest;
use App\Http\Resources\PaymentLinkResource;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Services\PaymentLinkService;
use App\Services\PaymentReconciliationService;
use App\Support\BankStatementCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Enlaces de pago del banco emitidos a mano.
 *
 * Todo el trabajo delicado vive en PaymentLinkService; aquí solo se resuelve
 * quién puede hacer qué.
 */
class PaymentLinkController extends Controller
{
    public function __construct(private readonly PaymentLinkService $service) {}

    /**
     * Cola del back-office: por defecto, lo que está esperando una decisión.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $links = PaymentLink::query()
            ->with(['payment.payable.bookable', 'payment.payable.user', 'media'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->whereIn('status', explode(',', $request->string('status')->toString()));
            })
            ->when($request->boolean('open'), function ($query) {
                $query->whereIn('status', PaymentLink::OPEN_STATUSES);
            })
            ->latest('id')
            ->jsonPaginate();

        return PaymentLinkResource::collection($links);
    }

    public function show(Request $request, PaymentLink $paymentLink): PaymentLinkResource
    {
        $this->ensureOwnerOrAdmin($request, $paymentLink);

        return PaymentLinkResource::make($paymentLink->load(['payment.payable.bookable', 'payment.payable.user', 'media']));
    }

    /**
     * El agente pega la URL generada en el portal del banco y se le envía al cliente.
     */
    public function attach(PaymentLinkAttachRequest $request, PaymentLink $paymentLink): PaymentLinkResource
    {
        $attrs = $request->validated()['data']['attributes'];

        $link = $this->service->attachUrl(
            $paymentLink,
            $attrs['url'],
            isset($attrs['expires_at']) ? Carbon::parse($attrs['expires_at']) : null,
            $request->user(),
        );

        return PaymentLinkResource::make($link->load(['payment.payable.bookable', 'payment.payable.user']));
    }

    /** Reenvío del enlace: el cliente perdió el correo. */
    public function send(Request $request, PaymentLink $paymentLink): PaymentLinkResource
    {
        $link = $this->service->send($paymentLink);

        return PaymentLinkResource::make($link->load(['payment.payable.bookable', 'payment.payable.user']));
    }

    /**
     * El cliente declara que ya pagó y, si quiere, adjunta el comprobante.
     *
     * Multipart, así que va sin las cabeceras JSON:API.
     */
    public function report(Request $request, PaymentLink $paymentLink): PaymentLinkResource
    {
        $this->ensureOwnerOrAdmin($request, $paymentLink);

        $datos = $request->validate([
            'authorization' => ['sometimes', 'nullable', 'string', 'max:40'],
            // Es un fichero de un usuario final: tipos cerrados y tamaño acotado.
            'proof' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $link = $this->service->report(
            $paymentLink,
            $datos['authorization'] ?? null,
            $request->file('proof'),
        );

        return PaymentLinkResource::make($link->load(['payment.payable.bookable', 'payment.payable.user', 'media']));
    }

    /**
     * Descarga del comprobante subido por el cliente.
     *
     * Vive en disco privado: sin esta ruta no hay forma de llegar al fichero, que
     * es justo lo que se quiere cuando puede llevar una tarjeta a la vista.
     */
    public function proof(Request $request, PaymentLink $paymentLink): StreamedResponse
    {
        $this->ensureOwnerOrAdmin($request, $paymentLink);

        $media = $paymentLink->getFirstMedia('payment_proof');

        abort_if($media === null, 404);

        $disk = Storage::disk($media->disk);
        $ruta = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($ruta), 404);

        return $disk->download(
            $ruta,
            'comprobante-'.$paymentLink->reference.'.'.pathinfo($media->file_name, PATHINFO_EXTENSION),
        );
    }

    /** Confirmación del cobro tras cotejarlo en el portal del banco. */
    public function confirm(PaymentLinkConfirmRequest $request, PaymentLink $paymentLink): PaymentLinkResource
    {
        $attrs = $request->validated()['data']['attributes'];

        $link = $this->service->confirm($paymentLink, [
            'authorization' => $attrs['authorization'],
            'charged' => (float) $attrs['charged'],
            // Normalizada a la zona del servidor: Carbon conserva el desfase con
            // el que llega y Eloquent guarda los componentes tal cual, así que
            // un ISO con zona se almacenaba movido. Aquí se fija el instante.
            'paid_at' => Carbon::parse($attrs['paid_at'])->setTimezone(config('app.timezone', 'UTC')),
            'note' => $attrs['note'] ?? null,
        ], $request->user());

        return PaymentLinkResource::make($link->load(['payment.payable.bookable', 'payment.payable.user']));
    }

    /** Anulación del enlace o reverso de un cobro confirmado por error. */
    public function void(Request $request, PaymentLink $paymentLink): PaymentLinkResource
    {
        $datos = $request->validate([
            'data.attributes.reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $link = $this->service->void(
            $paymentLink,
            $datos['data']['attributes']['reason'],
            $request->user(),
        );

        return PaymentLinkResource::make($link->load(['payment.payable.bookable', 'payment.payable.user']));
    }

    /**
     * POST /payment-links/reconcile
     *
     * Cruza el CSV exportado del portal del banco contra lo que tenemos
     * registrado. De solo lectura: no confirma nada por sí solo, solo informa
     * qué convendría revisar. Confirmar sigue pasando por `confirm()`, uno por
     * uno, con la autorización y el importe que el agente vio en el portal.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $request->validate([
            // .csv o .txt (algunos portales exportan con esa extensión pero
            // contenido separado por comas o punto y coma igualmente).
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $filas = BankStatementCsv::parse($request->file('statement')->get());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => [['title' => 'reconciliation.unreadable', 'detail' => $e->getMessage()]]], 422);
        }

        $reporte = app(PaymentReconciliationService::class)->reconcile($filas);

        return response()->json([
            'data' => [
                'type' => 'payment-reconciliation',
                'attributes' => $reporte,
            ],
        ]);
    }

    /**
     * Enlace abierto de una reserva, para que el cliente pueda volver a él.
     */
    public function forBooking(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->can('payments:view-all') || $booking->user_id === $user->id,
            403
        );

        $link = $this->service->openLinkFor($booking);

        return response()->json([
            'data' => $link
                ? PaymentLinkResource::make($link->load(['payment.payable.bookable', 'payment.payable.user', 'media']))->toArray($request)
                : null,
        ]);
    }

    /**
     * Solo el dueño de la reserva o el back-office ven un enlace.
     */
    private function ensureOwnerOrAdmin(Request $request, PaymentLink $link): void
    {
        $user = $request->user();

        if ($user->can('payments:view-all')) {
            return;
        }

        $booking = $link->booking();

        abort_unless($booking !== null && $booking->user_id === $user->id, 403);
    }
}
