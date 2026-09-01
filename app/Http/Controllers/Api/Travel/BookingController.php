<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Notifications\BookingNotification;
use App\Notifications\BookingReceiptNotification;
use App\Services\BookingService;
use App\Services\TourAvailabilityService;
use App\Support\AdminAlerts;
use App\Support\SiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly TourAvailabilityService $availabilityService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = auth()->user();
        $isAdmin = $user->can('bookings:view-all');

        $bookings = Booking::query()
            ->with(['user', 'transportDetail', 'upgradeVehicle', 'coupon', 'latestPayment', 'bookable' => fn (MorphTo $m) => $m->morphWith([Tour::class => ['category']])])
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('booking_type'), fn ($q) => $q->where('bookable_type', Booking::bookableClassFor($request->string('booking_type')->toString())))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('starts_at', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('starts_at', '<=', $request->string('date_to')->toString()))
            ->latest()
            ->sparseFieldset()
            ->jsonPaginate();

        return BookingResource::collection($bookings);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->ensureOwnerOrAdmin($request, $booking);

        $booking->load(['user', 'transportDetail', 'upgradeVehicle', 'coupon', 'latestPayment', 'bookable' => fn (MorphTo $m) => $m->morphWith([Tour::class => ['category']])]);

        return BookingResource::make($booking);
    }

    public function store(BookingRequest $request): BookingResource
    {
        $attrs = $request->validated()['data']['attributes'];

        // El cliente reserva para sí; un admin (agendamiento presencial) puede asignar
        // la reserva a un cliente existente o crear uno nuevo por correo.
        $targetUser = $this->resolveBookingCustomer($request);

        $booking = $this->bookingService->create(
            $targetUser,
            $attrs['booking_type'],
            $attrs
        );

        // Presencial: el admin puede confirmar de una vez (dispara la notificación de confirmación).
        if ($this->isAdmin($request) && $request->input('data.attributes.status') === Booking::STATUS_CONFIRMED) {
            $booking = $this->bookingService->changeStatus($booking, Booking::STATUS_CONFIRMED);
        }

        return BookingResource::make($this->loadRelations($booking));
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()->can('bookings:view-all');
    }

    /**
     * Determina el usuario dueño de la reserva. Un cliente reserva para sí mismo;
     * un admin puede indicar un cliente existente (customer.id / user_id) o uno nuevo (customer.email).
     */
    private function resolveBookingCustomer(Request $request): User
    {
        $auth = $request->user();

        if (! $this->isAdmin($request)) {
            return $auth;
        }

        $customer = $request->input('data.attributes.customer');
        $userId = $request->input('data.attributes.user_id');

        if (is_array($customer) && (! empty($customer['id']) || ! empty($customer['email']))) {
            $request->validate([
                'data.attributes.customer.id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
                'data.attributes.customer.email' => ['required_without:data.attributes.customer.id', 'nullable', 'email', 'max:255'],
                'data.attributes.customer.name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'data.attributes.customer.phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            ]);

            return ! empty($customer['id'])
                ? User::findOrFail($customer['id'])
                : $this->findOrCreateCustomer($customer);
        }

        if ($userId) {
            return User::findOrFail($userId);
        }

        return $auth;
    }

    private function findOrCreateCustomer(array $c): User
    {
        $existing = User::where('email', $c['email'])->first();
        if ($existing) {
            return $existing;
        }

        $name = trim($c['name'] ?? '') ?: 'Cliente';
        $parts = preg_split('/\s+/', $name, 2);

        $user = User::create([
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? '',
            'email' => $c['email'],
            'phone' => $c['phone'] ?? null,
            'username' => $this->uniqueUsername($c['email']),
            'password' => Hash::make(Str::random(40)),
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', Str::before($email, '@'))) ?: 'cliente';
        $username = $base;
        while (User::where('username', $username)->exists()) {
            $username = $base.random_int(100, 9999);
        }

        return $username;
    }

    public function update(BookingRequest $request, Booking $booking): BookingResource
    {
        // El dueño de la reserva o un administrador pueden cambiar el estado.
        // Esto cierra el IDOR (no se pueden modificar reservas ajenas) sin romper
        // el flujo en que el cliente gestiona la suya.
        $this->ensureOwnerOrAdmin($request, $booking);

        $status = $request->validated()['data']['attributes']['status'];

        // Confirmar una reserva es una decisión de back-office: implica darla por
        // buena y dispara la notificación al cliente. El dueño solo puede cancelar
        // la suya (para eso está POST /bookings/{id}/cancel).
        if ($status !== Booking::STATUS_CANCELLED && ! $this->isAdmin($request)) {
            abort(403);
        }

        $booking = $this->bookingService->changeStatus($booking, $status);

        return BookingResource::make($booking);
    }

    /**
     * Cancelar una reserva (solo si está pendiente / sin pagar). Notifica al cliente y a admins.
     */
    public function cancel(Request $request, Booking $booking): BookingResource
    {
        $this->ensureOwnerOrAdmin($request, $booking);

        if ($booking->status !== Booking::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['message.bookingNotCancellable'],
            ]);
        }

        // Ventana de cancelación GLOBAL: el cliente no puede cancelar dentro de las
        // últimas N horas antes del inicio (el admin sí puede).
        $cancellationHours = SiteSettings::cancellationHours();
        $isAdmin = $request->user()?->can('bookings:view-all') ?? false;
        if (! $isAdmin && $cancellationHours > 0 && $booking->starts_at) {
            $deadline = $booking->starts_at->copy()->subHours($cancellationHours);
            if (now()->greaterThan($deadline)) {
                throw ValidationException::withMessages([
                    'status' => ["Las reservas solo pueden cancelarse hasta {$cancellationHours} hora(s) antes del inicio."],
                ]);
            }
        }

        $booking = $this->bookingService->changeStatus($booking, Booking::STATUS_CANCELLED);

        $title = $this->bookableTitle($booking);
        $booking->user?->notify(new BookingNotification(
            'Reserva cancelada',
            "Tu reserva de \"{$title}\" fue cancelada.",
            ['reserva' => '#'.$booking->id]
        ));
        $this->notifyAdmins('Reserva cancelada por el cliente', 'Una reserva fue cancelada.', [
            'reserva' => '#'.$booking->id,
            'detalle' => $title,
        ]);

        return BookingResource::make($this->loadRelations($booking));
    }

    /**
     * Reagendar fecha(s) de la reserva (valida disponibilidad). Notifica al cliente y a admins.
     */
    public function reschedule(Request $request, Booking $booking): BookingResource
    {
        $this->ensureOwnerOrAdmin($request, $booking);

        if ($booking->status === Booking::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['date' => ['message.bookingCancelledNoReschedule']]);
        }

        if ($booking->booking_type === Booking::TYPE_TOUR) {
            $data = $request->validate(['date' => ['required', 'date', 'after:today']]);

            // Valida cupo del tour en la nueva fecha (considera cierres, override y reservas existentes).
            $capacity = $this->availabilityService->availableCapacity($booking->bookable, $data['date']);
            if ($capacity < (int) $booking->party_size) {
                throw ValidationException::withMessages(['date' => ['message.tourUnavailableForDate']]);
            }

            $booking->update(['starts_at' => $data['date']]);
        } else {
            $data = $request->validate([
                'pickup_at' => ['required', 'date', 'after:now'],
                'dropoff_at' => ['required', 'date', 'after:pickup_at'],
            ]);

            $overlap = Booking::query()
                ->where('bookable_type', TransportVehicle::class)
                ->where('bookable_id', $booking->bookable_id)
                ->where('id', '!=', $booking->id)
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
                ->where('starts_at', '<', $data['dropoff_at'])
                ->where('ends_at', '>', $data['pickup_at'])
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['pickup_at' => ['message.vehicleUnavailableForDates']]);
            }

            $booking->update(['starts_at' => $data['pickup_at'], 'ends_at' => $data['dropoff_at']]);
        }

        $booking->refresh();
        $title = $this->bookableTitle($booking);
        $booking->user?->notify(new BookingNotification(
            'Reserva reagendada',
            "Tu reserva de \"{$title}\" fue reagendada.",
            ['reserva' => '#'.$booking->id, 'nueva_fecha' => $booking->starts_at?->toDayDateTimeString()]
        ));
        $this->notifyAdmins('Reserva reagendada por el cliente', 'Una reserva cambió de fecha.', [
            'reserva' => '#'.$booking->id,
            'detalle' => $title,
        ]);

        return BookingResource::make($this->loadRelations($booking));
    }

    /**
     * Enviar un mensaje/consulta sobre la reserva. Notifica a los administradores.
     */
    public function sendMessage(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwnerOrAdmin($request, $booking);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        $title = $this->bookableTitle($booking);
        $this->notifyAdmins('Nuevo mensaje sobre una reserva', 'Un cliente envió un mensaje sobre su reserva.', [
            'reserva' => '#'.$booking->id,
            'detalle' => $title,
            'mensaje' => $data['message'],
        ]);
        $request->user()->notify(new BookingNotification(
            'Mensaje recibido',
            'Recibimos tu mensaje sobre la reserva y te responderemos pronto.',
            ['reserva' => $title]
        ));

        return response()->json([
            'data' => [
                'type' => 'booking-messages',
                'attributes' => ['status' => true, 'message' => 'message.bookingMessageSent'],
            ],
        ]);
    }

    /**
     * Descargar el comprobante de la reserva en PDF.
     */
    public function receipt(Request $request, Booking $booking)
    {
        $this->ensureOwnerOrAdmin($request, $booking);

        $this->loadRelations($booking);
        $pdf = Pdf::loadView('pdf.booking-receipt', [
            'booking' => $booking,
            'attrs' => (new BookingResource($booking))->toJsonApi(),
        ]);

        return $pdf->download('comprobante-reserva-'.$booking->id.'.pdf');
    }

    /**
     * Enlace de WhatsApp para que un agente acompañe el pago de la reserva.
     *
     * El mensaje se compone en el servidor a partir de la reserva ya guardada:
     * así el importe y el detalle que lee el agente son los que calculó el
     * backend, no los que diga el navegador.
     */
    public function whatsappLink(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwnerOrAdmin($request, $booking);

        if (! SiteSettings::whatsappPaymentEnabled()) {
            throw ValidationException::withMessages([
                'payment' => ['message.whatsappPaymentDisabled'],
            ]);
        }

        $number = SiteSettings::whatsappNumber();

        if ($number === '') {
            throw ValidationException::withMessages([
                'payment' => ['message.whatsappNumberMissing'],
            ]);
        }

        $booking->loadMissing(['bookable', 'transportDetail', 'coupon', 'user', 'upgradeVehicle']);

        $url = 'https://wa.me/'.$number.'?text='.rawurlencode($this->whatsappMessage($booking));

        // El cliente se queda con un comprobante en PDF: la reserva está apartada
        // aunque el pago siga pendiente.
        $booking->user?->notify(new BookingReceiptNotification($booking, $url));

        // El agente debe enterarse aunque el cliente no llegue a enviar el mensaje.
        $this->notifyAdmins('Solicitud de pago por WhatsApp', 'Un cliente pidió ayuda para completar el pago de su reserva.', [
            'reserva' => '#'.$booking->id,
            'detalle' => $this->bookableTitle($booking),
            'total' => number_format((float) $booking->total_price, 2).' '.($booking->currency_code ?: SiteSettings::currency()),
            'cliente' => trim($booking->user?->name ?? '').' · '.($booking->user?->email ?? ''),
        ]);

        return response()->json([
            'data' => [
                'type' => 'booking-whatsapp',
                'id' => (string) $booking->id,
                'attributes' => [
                    'url' => $url,
                    'phone' => $number,
                    'receipt_sent' => (bool) $booking->user?->email,
                ],
            ],
        ]);
    }

    /**
     * Texto de la solicitud de pago.
     *
     * Lleva todo lo que el agente necesita sin salir del chat: qué se contrata,
     * cuándo, para cuántos, el desglose del precio, los datos del cliente y los
     * enlaces al producto y a la reserva ya creada en el panel.
     */
    private function whatsappMessage(Booking $booking): string
    {
        $esTransporte = $booking->booking_type === Booking::TYPE_TRANSPORT;
        $moneda = $booking->currency_code ?: SiteSettings::currency();
        $front = rtrim((string) config('app.frontend_url'), '/');

        $l = ['¡Hola! Quiero completar el pago de mi reserva.', ''];

        // ── Qué se contrata ──
        $l[] = '*RESERVA #'.$booking->id.'*';
        $l[] = ($esTransporte ? 'Vehículo: ' : 'Tour: ').$this->bookableTitle($booking);

        if ($booking->bookable) {
            $l[] = 'Ficha: '.$front.($esTransporte ? '/transport/' : '/tours/').$booking->bookable->getKey();
        }

        // ── Cuándo y para cuántos ──
        if ($esTransporte) {
            $l[] = 'Recogida: '.$booking->starts_at?->format('d/m/Y H:i');
            $l[] = 'Devolución: '.$booking->ends_at?->format('d/m/Y H:i');
            $l[] = 'Unidades: '.$booking->party_size;

            if ($booking->transportDetail) {
                $l[] = 'Desde: '.$booking->transportDetail->pickup_location;
                $l[] = 'Hasta: '.$booking->transportDetail->dropoff_location;
                $l[] = 'Modalidad: '.($booking->transportDetail->rental_type === 'daily' ? 'por día' : 'por hora');
            }
        } else {
            $l[] = 'Fecha: '.$booking->starts_at?->format('d/m/Y');
            $l[] = 'Personas: '.$booking->party_size;
        }

        // ── Extras contratados ──
        $extras = is_array($booking->service_fees) ? $booking->service_fees : [];

        if ($extras !== []) {
            $l[] = '';
            $l[] = '*Servicios incluidos*';
            foreach ($extras as $extra) {
                $etiqueta = ($extra['calc'] ?? 'fixed') === 'per_person'
                    ? ' (x'.$booking->party_size.')'
                    : '';
                $l[] = '• '.($extra['name'] ?? 'Servicio').$etiqueta.': '.$this->money($extra['total'] ?? 0, $moneda);
            }
        }

        if ($booking->upgrade_label) {
            $l[] = 'Vehículo elegido: '.$booking->upgrade_label.' (+'.$this->money($booking->upgrade_surcharge, $moneda).')';
        }

        if ($booking->pickup_address) {
            $l[] = 'Punto de recogida: '.$booking->pickup_address;
        }

        if ($booking->notes) {
            $l[] = 'Notas: '.$booking->notes;
        }

        // ── Precio ──
        $l[] = '';
        if ((float) $booking->discount_amount > 0) {
            $cupon = $booking->coupon?->code ? ' ('.$booking->coupon->code.')' : '';
            $l[] = 'Descuento'.$cupon.': -'.$this->money($booking->discount_amount, $moneda);
        }
        $l[] = '*TOTAL: '.$this->money($booking->total_price, $moneda).'*';
        $l[] = 'Estado: pendiente de pago';

        // ── Cliente ──
        $cliente = $booking->user;
        if ($cliente) {
            $l[] = '';
            $l[] = '*Cliente*';
            $l[] = trim($cliente->name);
            $l[] = $cliente->email;
            if ($cliente->phone) {
                $l[] = $cliente->phone;
            }
        }

        // ── Atajo al panel para el agente ──
        $l[] = '';
        $l[] = 'Gestionar la reserva: '.$front.($esTransporte ? '/admin/vehicles?tab=reservas' : '/admin/tours?tab=agendados');

        return implode("\n", $l);
    }

    private function money(mixed $amount, string $currency): string
    {
        return number_format((float) $amount, 2, '.', ',').' '.$currency;
    }

    private function loadRelations(Booking $booking): Booking
    {
        return $booking->load(['user', 'transportDetail', 'upgradeVehicle', 'coupon', 'latestPayment', 'bookable' => fn (MorphTo $m) => $m->morphWith([Tour::class => ['category']])]);
    }

    private function bookableTitle(Booking $booking): string
    {
        return $booking->bookable?->title ?? ('Reserva #'.$booking->id);
    }

    private function notifyAdmins(string $title, string $body, array $details = []): void
    {
        AdminAlerts::send($title, $body, $details);
    }

    /**
     * Permite el acceso solo al dueño de la reserva o a un administrador.
     */
    private function ensureOwnerOrAdmin(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('bookings:view-all') || $booking->user_id === $user->id,
            403
        );
    }
}
