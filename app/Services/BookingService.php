<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\ReservationConfirmedNotification;
use App\Services\Booking\BookingHandlerInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BookingService
{
    private array $handlers = [];

    public function __construct(private readonly CouponService $couponService) {}

    public function registerHandler(string $type, BookingHandlerInterface $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function create(User $user, string $bookingType, array $data): Booking
    {
        $handler = $this->handlers[$bookingType]
            ?? throw new \InvalidArgumentException("No handler registered for booking type: {$bookingType}");

        return DB::transaction(function () use ($user, $bookingType, $handler, $data): Booking {
            $handler->validate($data);
            $prepared = $handler->prepare($data);
            $details = Arr::pull($prepared, 'details');

            // Cupón (opcional): descuenta sobre el total ya calculado (tarifa + extras + upgrade).
            $coupon = null;
            if (! empty($data['coupon_code'])) {
                $subtotal = (float) $prepared['total_price'];
                $coupon = $this->couponService->validate($data['coupon_code'], [
                    'user_id' => $user->id,
                    'booking_type' => $bookingType,
                    'pax' => (int) ($prepared['party_size'] ?? 0),
                    'subtotal' => $subtotal,
                ]);

                $discount = $coupon->discountFor($subtotal);
                $prepared['coupon_id'] = $coupon->id;
                $prepared['discount_amount'] = $discount;
                $prepared['total_price'] = round($subtotal - $discount, 2);
            }

            $booking = Booking::create(array_merge($prepared, [
                'user_id' => $user->id,
                'status' => Booking::STATUS_PENDING,
            ]));

            if ($details !== null) {
                $booking->transportDetail()->create($details);
            }

            if ($coupon !== null) {
                $this->couponService->redeem($coupon);
            }

            return $booking;
        });
    }

    public function changeStatus(Booking $booking, string $status): Booking
    {
        return DB::transaction(function () use ($booking, $status): Booking {
            $wasCancelled = $booking->status === Booking::STATUS_CANCELLED;
            $booking->update(['status' => $status]);

            // Al cancelar una reserva con cupón, se libera el uso (si no estaba ya cancelada).
            if ($status === Booking::STATUS_CANCELLED && ! $wasCancelled && $booking->coupon_id) {
                $booking->loadMissing('coupon');
                if ($booking->coupon) {
                    $this->couponService->release($booking->coupon);
                }
            }

            // La notificación la dispara BookingObserver::updated al detectar el
            // cambio de estado. Hacerlo también aquí enviaba el correo (y su
            // comprobante adjunto) por duplicado.

            return $booking->refresh();
        });
    }

    public function notifyConfirmation(Booking $booking): void
    {
        $booking->loadMissing(['user', 'bookable', 'invoice', 'transportDetail']);

        if ($booking->user) {
            $isTransport = $booking->booking_type === Booking::TYPE_TRANSPORT;

            $detalles = $isTransport ? [
                'booking_id' => $booking->id,
                'vehicle' => $booking->bookable?->title,
                'pickup_at' => $booking->starts_at?->toDateTimeString(),
            ] : [
                'booking_id' => $booking->id,
                'tour' => $booking->bookable?->title,
                'booking_date' => $booking->starts_at?->toDateString(),
            ];

            // El número de factura viaja en el mismo correo, que ya lleva el
            // comprobante adjunto. Antes salía un segundo correo anunciando una
            // factura que no acompañaba ningún documento.
            if ($booking->invoice) {
                $detalles['invoice'] = 'INV-'.str_pad((string) $booking->invoice->id, 6, '0', STR_PAD_LEFT);
            }

            $booking->user->notify(new ReservationConfirmedNotification(
                $isTransport ? 'Your transport booking is confirmed' : 'Your tour booking is confirmed',
                $isTransport ? 'Your vehicle rental has been confirmed successfully.' : 'Your trip booking has been confirmed successfully.',
                $detalles,
                $booking,
            ));
        }

        foreach (config('services.notifications.admin_emails', []) as $email) {
            Notification::route('mail', $email)->notify(new AdminAlertNotification(
                'Booking confirmed',
                'A booking has been confirmed and may require back-office attention.',
                [
                    'booking_id' => $booking->id,
                    'booking_type' => $booking->booking_type,
                    'bookable_id' => $booking->bookable_id,
                ]
            ));
        }
    }
}
