<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $bookings = Booking::query()
            ->with(['user', 'bookable', 'transportDetail', 'latestPayment'])
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('booking_type'), fn ($q) => $q->where('booking_type', $request->string('booking_type')->toString()))
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

        $booking->load(['user', 'bookable', 'transportDetail', 'latestPayment']);
        return BookingResource::make($booking);
    }

    public function store(BookingRequest $request): BookingResource
    {
        $attrs = $request->validated()['data']['attributes'];

        $booking = $this->bookingService->create(
            $request->user(),
            $attrs['booking_type'],
            $attrs
        );

        return BookingResource::make($booking);
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
