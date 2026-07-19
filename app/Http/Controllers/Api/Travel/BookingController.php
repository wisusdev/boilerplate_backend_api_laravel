<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Role;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\BookingNotification;
use App\Services\BookingService;
use App\Services\TourAvailabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly TourAvailabilityService $availabilityService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

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
        return $request->user()->hasRole(['admin', 'super-admin']);
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
        $userId   = $request->input('data.attributes.user_id');

        if (is_array($customer) && (! empty($customer['id']) || ! empty($customer['email']))) {
            $request->validate([
                'data.attributes.customer.id'    => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
                'data.attributes.customer.email' => ['required_without:data.attributes.customer.id', 'nullable', 'email', 'max:255'],
                'data.attributes.customer.name'  => ['sometimes', 'nullable', 'string', 'max:255'],
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

        $name  = trim($c['name'] ?? '') ?: 'Cliente';
        $parts = preg_split('/\s+/', $name, 2);

        $user = User::create([
            'first_name' => $parts[0],
            'last_name'  => $parts[1] ?? '',
            'email'      => $c['email'],
            'phone'      => $c['phone'] ?? null,
            'username'   => $this->uniqueUsername($c['email']),
            'password'   => Hash::make(Str::random(40)),
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', Str::before($email, '@'))) ?: 'cliente';
        $username = $base;
        while (User::where('username', $username)->exists()) {
            $username = $base . random_int(100, 9999);
        }

        return $username;
    }

    public function update(BookingRequest $request, Booking $booking): BookingResource
    {
        // El dueño de la reserva o un administrador pueden cambiar el estado.
        // Esto cierra el IDOR (no se pueden modificar reservas ajenas) sin romper
        // el flujo en que el cliente gestiona la suya.
        $this->ensureOwnerOrAdmin($request, $booking);

        $booking = $this->bookingService->changeStatus(
            $booking,
            $request->validated()['data']['attributes']['status']
        );

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

        $booking = $this->bookingService->changeStatus($booking, Booking::STATUS_CANCELLED);

        $title = $this->bookableTitle($booking);
        $booking->user?->notify(new BookingNotification(
            'Reserva cancelada',
            "Tu reserva de \"{$title}\" fue cancelada.",
            ['reserva' => '#' . $booking->id]
        ));
        $this->notifyAdmins('Reserva cancelada por el cliente', 'Una reserva fue cancelada.', [
            'reserva' => '#' . $booking->id,
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
                'pickup_at'  => ['required', 'date', 'after:now'],
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
            ['reserva' => '#' . $booking->id, 'nueva_fecha' => $booking->starts_at?->toDayDateTimeString()]
        ));
        $this->notifyAdmins('Reserva reagendada por el cliente', 'Una reserva cambió de fecha.', [
            'reserva' => '#' . $booking->id,
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
            'user_id'    => $request->user()->id,
            'message'    => $data['message'],
        ]);

        $title = $this->bookableTitle($booking);
        $this->notifyAdmins('Nuevo mensaje sobre una reserva', 'Un cliente envió un mensaje sobre su reserva.', [
            'reserva' => '#' . $booking->id,
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
                'type'       => 'booking-messages',
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
            'attrs'   => (new BookingResource($booking))->toJsonApi(),
        ]);

        return $pdf->download('comprobante-reserva-' . $booking->id . '.pdf');
    }

    private function loadRelations(Booking $booking): Booking
    {
        return $booking->load(['user', 'transportDetail', 'upgradeVehicle', 'coupon', 'latestPayment', 'bookable' => fn (MorphTo $m) => $m->morphWith([Tour::class => ['category']])]);
    }

    private function bookableTitle(Booking $booking): string
    {
        return $booking->bookable?->title ?? ('Reserva #' . $booking->id);
    }

    private function notifyAdmins(string $title, string $body, array $details = []): void
    {
        // Solo roles que existen: el scope role() de Spatie lanza excepción con un rol inexistente.
        $roleNames = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('name', ['admin', 'super-admin'])
            ->pluck('name')
            ->all();

        if (empty($roleNames)) {
            return;
        }

        $emails = User::role($roleNames, 'api')->pluck('email')->filter()->unique();
        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify(new AdminAlertNotification($title, $body, $details));
        }
    }

    /**
     * Permite el acceso solo al dueño de la reserva o a un administrador.
     */
    private function ensureOwnerOrAdmin(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole(['admin', 'super-admin']) || $booking->user_id === $user->id,
            403
        );
    }
}
