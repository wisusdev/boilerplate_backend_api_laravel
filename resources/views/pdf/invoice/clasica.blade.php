<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        /* Clásica: corporativa, banda de color y tabla marcada. */
        @page { margin: 34px 40px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 11px; line-height: 1.45; }
        .r { text-align: right; } .c { text-align: center; }
        .muted { color: #6b7280; }

        .hd { width: 100%; border-collapse: collapse; }
        .hd td { vertical-align: top; padding: 0; }
        .logo { max-height: 46px; max-width: 190px; }
        .marca { font-size: 16px; font-weight: bold; color: #184ca0; }
        .linea { color: #6b7280; font-size: 10px; }
        .tipo { font-size: 19px; font-weight: bold; color: #184ca0; letter-spacing: 1px; }
        .num { font-size: 13px; font-weight: bold; }
        .regla { border-bottom: 3px solid #184ca0; margin-top: 14px; }

        .partes { width: 100%; margin-top: 18px; border-collapse: separate; }
        .partes td { width: 50%; vertical-align: top; padding-right: 14px; }
        .caja { background: #f8fafc; border: 1px solid #e5e7eb; padding: 10px 12px; }
        .caja h2 { font-size: 9px; margin: 0 0 6px; color: #6b7280; text-transform: uppercase; letter-spacing: .8px; }
        .caja .nombre { font-weight: bold; font-size: 12px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th { background: #184ca0; color: #fff; font-size: 9px; padding: 7px 8px;
                         text-align: left; text-transform: uppercase; letter-spacing: .6px; }
        table.items td { padding: 8px; border-bottom: 1px solid #eef2f7; }
        .tot { width: 46%; border-collapse: collapse; margin-top: 12px; float: right; }
        .tot td { padding: 5px 8px; }
        .tot .final td { border-top: 2px solid #184ca0; font-size: 14px; font-weight: bold; color: #184ca0; }
        .notas { clear: both; padding-top: 26px; color: #6b7280; font-size: 10px; }
        .pie { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 10px;
               color: #9ca3af; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <table class="hd">
        <tr>
            <td style="width:58%">
                @if ($d['emisor']['logo'])
                    <img src="{{ $d['emisor']['logo'] }}" class="logo" alt="">
                    <div class="linea" style="margin-top:6px">{{ $d['emisor']['nombre'] }}</div>
                @else
                    <div class="marca">{{ $d['emisor']['nombre'] }}</div>
                @endif
                @foreach (array_merge($d['emisor']['fiscal'], $d['emisor']['lineas']) as $l)
                    <div class="linea">{{ $l }}</div>
                @endforeach
            </td>
            <td class="r">
                <div class="tipo">FACTURA</div>
                <div class="num">{{ $d['numero'] }}</div>
                <div class="linea" style="margin-top:4px">Emitida: {{ $d['emitido']?->format('d/m/Y') }}</div>
                @if ($d['reserva'])<div class="linea">Reserva: #{{ $d['reserva'] }}</div>@endif
                @if ($d['dteNumero'])<div class="linea">DTE: {{ $d['dteNumero'] }}</div>@endif
                <div class="linea">Estado: {{ $d['estadoEtiqueta'] }}</div>
            </td>
        </tr>
    </table>
    <div class="regla"></div>

    <table class="partes">
        <tr>
            <td>
                <div class="caja">
                    <h2>Facturar a</h2>
                    <div class="nombre">{{ $d['cliente']['nombre'] }}</div>
                    @if ($d['cliente']['documento'])<div class="muted">{{ $d['cliente']['documento'] }}</div>@endif
                    @if ($d['cliente']['correo'])<div class="muted">{{ $d['cliente']['correo'] }}</div>@endif
                </div>
            </td>
            <td style="padding-right:0">
                <div class="caja">
                    <h2>Resumen</h2>
                    <div><span class="muted">Conceptos:</span> {{ count($d['conceptos']) }}</div>
                    <div><span class="muted">Moneda:</span> {{ $d['moneda'] }}</div>
                    <div><span class="muted">Total:</span> <strong>{{ $d['moneda'] }} {{ number_format($d['total'], 2) }}</strong></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:54%">Descripción</th>
                <th class="c" style="width:10%">Cant.</th>
                <th class="r" style="width:18%">P. unitario</th>
                <th class="r" style="width:18%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['conceptos'] as $c)
                <tr>
                    <td>{{ $c['descripcion'] }}</td>
                    <td class="c">{{ $c['cantidad'] }}</td>
                    <td class="r">{{ $d['moneda'] }} {{ number_format($c['unitario'], 2) }}</td>
                    <td class="r">{{ $d['moneda'] }} {{ number_format($c['importe'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot">
        <tr><td class="muted">Subtotal</td><td class="r">{{ $d['moneda'] }} {{ number_format($d['subtotal'], 2) }}</td></tr>
        <tr class="final"><td>TOTAL</td><td class="r">{{ $d['moneda'] }} {{ number_format($d['total'], 2) }}</td></tr>
    </table>

    @if ($d['notas'])
        <div class="notas"><strong>Notas:</strong> {{ $d['notas'] }}</div>
    @endif

    <div class="pie">{{ $d['emisor']['nombre'] }} &middot; Gracias por tu preferencia.</div>
</body>
</html>
