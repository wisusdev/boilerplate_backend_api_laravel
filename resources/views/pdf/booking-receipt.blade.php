<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        /* DomPDF solo entiende CSS2: la maquetación va con tablas y floats,
           nunca con flexbox ni grid. */
        @page { margin: 34px 40px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 11px; line-height: 1.45; }

        .r { text-align: right; }
        .c { text-align: center; }
        .muted { color: #6b7280; }
        .upper { text-transform: uppercase; letter-spacing: .6px; }

        /* ── Cabecera ── */
        .hd { width: 100%; border-collapse: collapse; }
        .hd td { vertical-align: top; padding: 0; }
        .logo { max-height: 46px; max-width: 190px; }
        .emisor-nombre { font-size: 16px; font-weight: bold; color: #184ca0; }
        .emisor-linea { color: #6b7280; font-size: 10px; }
        .doc-tipo { font-size: 18px; font-weight: bold; color: #184ca0; letter-spacing: 1px; }
        .doc-num { font-size: 13px; font-weight: bold; }

        .regla { border-bottom: 2px solid #184ca0; margin: 14px 0 0; }

        /* ── Partes ── */
        .partes { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 18px; }
        .partes td { vertical-align: top; width: 50%; padding: 0 14px 0 0; }
        .partes td + td { padding: 0 0 0 14px; }
        .caja { background: #f8fafc; border: 1px solid #e5e7eb; padding: 10px 12px; }
        .caja h2 { font-size: 9px; margin: 0 0 6px; color: #6b7280; font-weight: bold;
                   text-transform: uppercase; letter-spacing: .8px; }
        .caja .nombre { font-weight: bold; font-size: 12px; }
        .caja div { margin-top: 1px; }

        /* ── Conceptos ── */
        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th { background: #184ca0; color: #fff; font-size: 9px; padding: 7px 8px;
                         text-align: left; text-transform: uppercase; letter-spacing: .6px; }
        table.items td { padding: 8px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        table.items .desc { font-weight: bold; }
        table.items .nota { color: #6b7280; font-size: 10px; font-weight: normal; }

        /* ── Totales ── */
        .totales { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .totales td { padding: 0; vertical-align: top; }
        .tot-tabla { width: 100%; border-collapse: collapse; }
        .tot-tabla td { padding: 5px 8px; }
        .tot-tabla .lbl { color: #6b7280; }
        .tot-final td { border-top: 2px solid #184ca0; font-size: 14px; font-weight: bold; color: #184ca0; padding-top: 8px; }

        /* ── Pago ── */
        .pago { margin-top: 4px; border: 1px solid #e5e7eb; padding: 10px 12px; }
        .pago h2 { font-size: 9px; margin: 0 0 6px; color: #6b7280; font-weight: bold;
                   text-transform: uppercase; letter-spacing: .8px; }
        .chip { display: inline-block; padding: 2px 9px; border-radius: 10px;
                font-size: 10px; font-weight: bold; }
        .ok { background: #dcfce7; color: #166534; }
        .pend { background: #fef9c3; color: #854d0e; }
        .mal { background: #fee2e2; color: #991b1b; }

        .pie { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 10px;
               color: #9ca3af; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    @php
        $isTour = ($attrs['booking_type'] ?? '') === 'tour';
        $moneda = $attrs['currency_code'] ?? 'USD';
        $money = fn ($n) => $moneda.' '.number_format((float) $n, 2);

        $estadoReserva = ['confirmed' => 'Confirmada', 'pending' => 'Pendiente', 'cancelled' => 'Cancelada'];
        $estadoPago = ['paid' => 'Pagado', 'pending' => 'Pendiente de pago', 'failed' => 'Fallido'];
        $chipPago = ['paid' => 'ok', 'pending' => 'pend', 'failed' => 'mal'];
        // Los métodos llegan como identificadores técnicos ('card', 'bank_transfer'):
        // sin traducirlos, el documento del cliente los mostraba en crudo.
        $metodoPago = [
            'card' => 'Tarjeta de crédito/débito',
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia bancaria',
            'paypal' => 'PayPal',
            'whatsapp' => 'Pago asistido por WhatsApp',
        ];

        $pagoEstado = $attrs['payment_status'] ?? 'pending';
        $reservaEstado = $attrs['status'] ?? 'pending';

        // ── Datos del emisor: ajustes del sitio, con los fiscales si están cargados ──
        $ajustes = \App\Models\Setting::where('key', 'app')->value('value');
        $ajustes = $ajustes ? (json_decode($ajustes, true) ?: []) : [];
        $fiscal = \App\Models\Setting::where('key', 'dte')->value('value');
        $fiscal = $fiscal ? (json_decode($fiscal, true) ?: []) : [];

        $emisor = trim($fiscal['dte_nombre'] ?? '') ?: \App\Support\SiteSettings::name();
        $emisorLineas = array_values(array_filter([
            trim($fiscal['dte_direccion'] ?? '') ?: trim($ajustes['contact_address'] ?? ''),
            trim(implode(', ', array_filter([$ajustes['contact_city'] ?? null, $ajustes['contact_country'] ?? null]))),
            trim($fiscal['dte_telefono'] ?? '') ?: trim($ajustes['contact_phone'] ?? ''),
            trim($fiscal['dte_correo'] ?? '') ?: trim($ajustes['contact_email'] ?? ''),
        ]));
        $emisorFiscal = array_values(array_filter([
            !empty($fiscal['dte_nit']) ? 'NIT: '.$fiscal['dte_nit'] : null,
            !empty($fiscal['dte_nrc']) ? 'NRC: '.$fiscal['dte_nrc'] : null,
        ]));

        // Misma lógica (y misma validación de que la ruta no se escapa de
        // storage/app/public) que usan las facturas: se reutiliza en vez de
        // duplicarla aquí.
        $logo = \App\Support\InvoiceDocument::logoPath($ajustes);

        // ── Conceptos: se reconstruye el desglose que originó el total ──
        $extras = is_array($attrs['service_fees'] ?? null) ? $attrs['service_fees'] : [];
        $upgrade = (float) ($attrs['upgrade_surcharge'] ?? 0);
        $descuento = (float) ($attrs['discount_amount'] ?? 0);
        $total = (float) ($attrs['total_price'] ?? 0);
        $extrasTotal = array_sum(array_map(fn ($e) => (float) ($e['total'] ?? 0), $extras));

        $cantidad = (int) ($isTour ? ($attrs['pax_count'] ?? 1) : ($attrs['quantity'] ?? 1)) ?: 1;
        // Tarifa base = total facturado + descuento − extras − upgrade.
        $baseTotal = round($total + $descuento - $extrasTotal - $upgrade, 2);

        $conceptos = [[
            'desc' => $isTour ? ($attrs['tour_title'] ?? 'Tour') : ($attrs['vehicle_title'] ?? 'Alquiler de vehículo'),
            'nota' => $isTour
                ? trim('Fecha: '.($attrs['booking_date'] ?? '—'))
                : trim(($attrs['pickup_at'] ?? '—').' → '.($attrs['dropoff_at'] ?? '—')),
            'cant' => $cantidad,
            'unit' => $cantidad > 0 ? round($baseTotal / $cantidad, 2) : $baseTotal,
            'importe' => $baseTotal,
        ]];

        foreach ($extras as $e) {
            $porPersona = ($e['calc'] ?? 'fixed') === 'per_person';
            $conceptos[] = [
                'desc' => $e['name'] ?? 'Servicio adicional',
                'nota' => $porPersona ? 'Por persona' : 'Cargo fijo',
                'cant' => $porPersona ? $cantidad : 1,
                'unit' => (float) ($e['amount'] ?? 0),
                'importe' => (float) ($e['total'] ?? 0),
            ];
        }

        if ($upgrade > 0) {
            $conceptos[] = [
                'desc' => $attrs['upgrade_label'] ?? 'Vehículo',
                'nota' => 'Mejora de transporte',
                'cant' => 1,
                'unit' => $upgrade,
                'importe' => $upgrade,
            ];
        }

        $subtotal = round(array_sum(array_map(fn ($c) => $c['importe'], $conceptos)), 2);
        $numero = $booking->invoice
            ? 'INV-'.str_pad((string) $booking->invoice->id, 6, '0', STR_PAD_LEFT)
            : 'RES-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT);
    @endphp

    {{-- ══ Cabecera: emisor a la izquierda, documento a la derecha ══ --}}
    <table class="hd">
        <tr>
            <td style="width:58%">
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="">
                    <div class="emisor-linea" style="margin-top:6px">{{ $emisor }}</div>
                @else
                    <div class="emisor-nombre">{{ $emisor }}</div>
                @endif
                @foreach ($emisorFiscal as $linea)
                    <div class="emisor-linea">{{ $linea }}</div>
                @endforeach
                @foreach ($emisorLineas as $linea)
                    <div class="emisor-linea">{{ $linea }}</div>
                @endforeach
            </td>
            <td class="r">
                <div class="doc-tipo">Comprobante</div>
                <div class="doc-num">N.º {{ $numero }}</div>
                <div class="emisor-linea" style="margin-top:4px">Emitido: {{ now()->format('d/m/Y H:i') }}</div>
                <div class="emisor-linea">Reserva: #{{ $booking->id }}</div>
                <div style="margin-top:6px">
                    <span class="chip {{ $chipPago[$pagoEstado] ?? 'pend' }}">
                        {{ $estadoPago[$pagoEstado] ?? $pagoEstado }}
                    </span>
                </div>
            </td>
        </tr>
    </table>
    <div class="regla"></div>

    {{-- ══ Partes ══ --}}
    <table class="partes">
        <tr>
            <td>
                <div class="caja">
                    <h2>Facturar a</h2>
                    <div class="nombre">{{ $attrs['user_name'] ?? '—' }}</div>
                    @if (!empty($attrs['user_email']))
                        <div class="muted">{{ $attrs['user_email'] }}</div>
                    @endif
                    @if (!empty($booking->user?->phone))
                        <div class="muted">{{ $booking->user->phone }}</div>
                    @endif
                </div>
            </td>
            <td>
                <div class="caja">
                    <h2>Detalle de la reserva</h2>
                    <div><span class="muted">Tipo:</span> {{ $isTour ? 'Tour' : 'Alquiler de vehículo' }}</div>
                    <div><span class="muted">Estado:</span> {{ $estadoReserva[$reservaEstado] ?? $reservaEstado }}</div>
                    @if ($isTour)
                        <div><span class="muted">Fecha:</span> {{ $attrs['booking_date'] ?? '—' }}</div>
                        <div><span class="muted">Pasajeros:</span> {{ $attrs['pax_count'] ?? '—' }}</div>
                        @if (!empty($attrs['pickup_address']))
                            <div><span class="muted">Recogida:</span> {{ $attrs['pickup_address'] }}</div>
                        @endif
                    @else
                        <div><span class="muted">Recogida:</span> {{ $attrs['pickup_at'] ?? '—' }}</div>
                        <div><span class="muted">Devolución:</span> {{ $attrs['dropoff_at'] ?? '—' }}</div>
                        @if (!empty($attrs['pickup_location']))
                            <div><span class="muted">Desde:</span> {{ $attrs['pickup_location'] }}</div>
                        @endif
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ══ Conceptos ══ --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:52%">Descripción</th>
                <th class="c" style="width:10%">Cant.</th>
                <th class="r" style="width:19%">P. unitario</th>
                <th class="r" style="width:19%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($conceptos as $c)
                <tr>
                    <td>
                        <div class="desc">{{ $c['desc'] }}</div>
                        @if (!empty($c['nota']))
                            <div class="nota">{{ $c['nota'] }}</div>
                        @endif
                    </td>
                    <td class="c">{{ $c['cant'] }}</td>
                    <td class="r">{{ $money($c['unit']) }}</td>
                    <td class="r">{{ $money($c['importe']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ══ Totales, con el pago a la izquierda para aprovechar el ancho ══ --}}
    <table class="totales">
        <tr>
            <td style="width:56%; padding-right:16px">
                <div class="pago">
                    <h2>Información de pago</h2>
                    <div><span class="muted">Estado:</span> {{ $estadoPago[$pagoEstado] ?? $pagoEstado }}</div>
                    @if (!empty($attrs['payment_method']))
                        <div><span class="muted">Método:</span> {{ $metodoPago[$attrs['payment_method']] ?? $attrs['payment_method'] }}</div>
                    @endif
                    @if (!empty($attrs['payment_reference']))
                        <div><span class="muted">Referencia:</span> {{ $attrs['payment_reference'] }}</div>
                    @endif
                    @if ($pagoEstado !== 'paid')
                        <div style="margin-top:5px" class="muted">
                            Esta reserva está apartada y pendiente de pago.
                        </div>
                    @endif
                </div>
            </td>
            <td style="width:44%">
                <table class="tot-tabla">
                    <tr>
                        <td class="lbl">Subtotal</td>
                        <td class="r">{{ $money($subtotal) }}</td>
                    </tr>
                    @if ($descuento > 0)
                        <tr>
                            <td class="lbl">
                                Descuento
                                @if (!empty($attrs['coupon_code']))
                                    <span class="nota">({{ $attrs['coupon_code'] }})</span>
                                @endif
                            </td>
                            <td class="r">− {{ $money($descuento) }}</td>
                        </tr>
                    @endif
                    <tr class="tot-final">
                        <td class="upper">Total</td>
                        <td class="r">{{ $money($total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="pie">
        Este comprobante refleja el estado de la reserva al momento de su emisión y no sustituye
        a un documento tributario electrónico.<br>
        {{ $emisor }} &middot; Gracias por tu preferencia.
    </div>
</body>
</html>
