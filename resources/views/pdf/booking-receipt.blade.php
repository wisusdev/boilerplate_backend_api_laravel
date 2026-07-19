<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 13px; }
        .head { border-bottom: 3px solid #184ca0; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { color: #184ca0; font-size: 22px; font-weight: bold; }
        .doc { color: #6b7280; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .b-confirmed { background: #dcfce7; color: #166534; }
        .b-pending { background: #fef9c3; color: #854d0e; }
        .b-cancelled { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 8px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.k { color: #6b7280; width: 38%; }
        td.v { font-weight: bold; }
        .total { font-size: 16px; color: #184ca0; }
        .foot { margin-top: 28px; color: #9ca3af; font-size: 11px; text-align: center; }
    </style>
</head>
<body>
    @php
        $isTour = ($attrs['booking_type'] ?? '') === 'tour';
        $statusClass = ['confirmed' => 'b-confirmed', 'pending' => 'b-pending', 'cancelled' => 'b-cancelled'][$attrs['status'] ?? ''] ?? 'b-pending';
        $statusLabel = ['confirmed' => 'Confirmada', 'pending' => 'Pendiente', 'cancelled' => 'Cancelada'][$attrs['status'] ?? ''] ?? ($attrs['status'] ?? '');
        $money = fn ($n) => ($attrs['currency_code'] ?? 'USD') . ' ' . number_format((float) $n, 2);
    @endphp

    <div class="head">
        <div class="brand">{{ config('app.name', 'Cusgo Adventures') }}</div>
        <div class="doc">Comprobante de reserva &middot; #{{ $booking->id }} &middot; emitido {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <h1>{{ $isTour ? ($attrs['tour_title'] ?? 'Tour') : ($attrs['vehicle_title'] ?? 'Transporte') }}</h1>
    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>

    <table>
        <tr><td class="k">Tipo</td><td class="v">{{ $isTour ? 'Tour' : 'Transporte' }}</td></tr>
        <tr><td class="k">Cliente</td><td class="v">{{ $attrs['user_name'] ?? '—' }}</td></tr>
        <tr><td class="k">Correo</td><td class="v">{{ $attrs['user_email'] ?? '—' }}</td></tr>

        @if ($isTour)
            <tr><td class="k">Fecha del tour</td><td class="v">{{ $attrs['booking_date'] ?? '—' }}</td></tr>
            <tr><td class="k">Pasajeros</td><td class="v">{{ $attrs['pax_count'] ?? '—' }}</td></tr>
            @if (!empty($attrs['upgrade_label']))
                <tr><td class="k">Vehículo</td><td class="v">{{ $attrs['upgrade_label'] }} &middot; +{{ $money($attrs['upgrade_surcharge'] ?? 0) }}</td></tr>
            @endif
            @if (!empty($attrs['pickup_address']) || !empty($attrs['pickup_lat']))
                <tr>
                    <td class="k">Punto de recogida</td>
                    <td class="v">
                        {{ $attrs['pickup_address'] ?? '—' }}
                        @if (!empty($attrs['pickup_lat']) && !empty($attrs['pickup_lng']))
                            <br><span style="font-weight:normal;color:#6b7280;">{{ $attrs['pickup_lat'] }}, {{ $attrs['pickup_lng'] }}</span>
                        @endif
                    </td>
                </tr>
            @endif
        @else
            <tr><td class="k">Recogida</td><td class="v">{{ $attrs['pickup_at'] ?? '—' }} &middot; {{ $attrs['pickup_location'] ?? '' }}</td></tr>
            <tr><td class="k">Devolución</td><td class="v">{{ $attrs['dropoff_at'] ?? '—' }} &middot; {{ $attrs['dropoff_location'] ?? '' }}</td></tr>
            <tr><td class="k">Cantidad</td><td class="v">{{ $attrs['quantity'] ?? 1 }}</td></tr>
        @endif

        <tr><td class="k">Estado del pago</td><td class="v">{{ $attrs['payment_status'] ?? 'Pendiente' }}</td></tr>
        @if (!empty($attrs['payment_method']))
            <tr><td class="k">Método de pago</td><td class="v">{{ $attrs['payment_method'] }}</td></tr>
        @endif
        @if (!empty($attrs['payment_reference']))
            <tr><td class="k">Referencia</td><td class="v">{{ $attrs['payment_reference'] }}</td></tr>
        @endif
        @if (!empty($attrs['discount_amount']) && (float) $attrs['discount_amount'] > 0)
            <tr>
                <td class="k">Descuento{{ !empty($attrs['coupon_code']) ? ' (' . $attrs['coupon_code'] . ')' : '' }}</td>
                <td class="v">− {{ $money($attrs['discount_amount']) }}</td>
            </tr>
        @endif
        <tr><td class="k">Total</td><td class="v total">{{ $money($attrs['total_price'] ?? 0) }}</td></tr>
    </table>

    <div class="foot">
        Este comprobante refleja el estado de la reserva al momento de su emisión.<br>
        {{ config('app.name', 'Cusgo Adventures') }} &middot; Gracias por tu preferencia.
    </div>
</body>
</html>
