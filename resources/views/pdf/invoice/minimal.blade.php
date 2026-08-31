<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        /* Minimalista: monocroma, sin rellenos, todo el peso en la tipografía. */
        @page { margin: 46px 52px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #111; font-size: 10.5px; line-height: 1.6; }
        .r { text-align: right; } .c { text-align: center; }
        .muted { color: #8a8a8a; }
        .etq { font-size: 8px; text-transform: uppercase; letter-spacing: 1.4px; color: #8a8a8a; }

        .hd { width: 100%; border-collapse: collapse; margin-bottom: 34px; }
        .hd td { vertical-align: top; }
        .logo { max-height: 34px; max-width: 150px; }
        .marca { font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .tipo { font-size: 11px; letter-spacing: 4px; text-transform: uppercase; }
        .num { font-size: 20px; font-weight: bold; letter-spacing: -.3px; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .meta td { vertical-align: top; width: 50%; padding-right: 20px; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items th { font-size: 8px; text-transform: uppercase; letter-spacing: 1.4px;
                         color: #8a8a8a; text-align: left; padding: 0 0 10px;
                         border-bottom: 1px solid #111; }
        table.items td { padding: 12px 0 11px; border-bottom: 1px solid #ededed; }

        .tot { width: 42%; float: right; border-collapse: collapse; margin-top: 16px; }
        .tot td { padding: 6px 0; }
        .tot .final td { border-top: 1px solid #111; padding-top: 11px;
                         font-size: 13px; font-weight: bold; letter-spacing: .3px; }
        .notas { clear: both; padding-top: 34px; font-size: 9.5px; color: #6a6a6a; }
        .pie { margin-top: 30px; font-size: 8px; letter-spacing: 1.2px;
               text-transform: uppercase; color: #b5b5b5; }
    </style>
</head>
<body>
    <table class="hd">
        <tr>
            <td style="width:55%">
                @if ($d['emisor']['logo'])
                    <img src="{{ $d['emisor']['logo'] }}" class="logo" alt="">
                @else
                    <div class="marca">{{ $d['emisor']['nombre'] }}</div>
                @endif
            </td>
            <td class="r">
                <div class="tipo">Factura</div>
                <div class="num">{{ $d['numero'] }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="etq">Facturar a</div>
                <div><strong>{{ $d['cliente']['nombre'] }}</strong></div>
                @if ($d['cliente']['documento'])<div class="muted">{{ $d['cliente']['documento'] }}</div>@endif
                @if ($d['cliente']['correo'])<div class="muted">{{ $d['cliente']['correo'] }}</div>@endif
            </td>
            <td class="r" style="padding-right:0">
                <div class="etq">Emitida</div>
                <div>{{ $d['emitido']?->format('d/m/Y') }}</div>
                <div class="muted">{{ $d['estadoEtiqueta'] }}</div>
                @if ($d['reserva'])<div class="muted">Reserva #{{ $d['reserva'] }}</div>@endif
                @if ($d['dteNumero'])<div class="muted">DTE {{ $d['dteNumero'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:58%">Concepto</th>
                <th class="c" style="width:9%">Cant.</th>
                <th class="r" style="width:16%">Precio</th>
                <th class="r" style="width:17%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['conceptos'] as $c)
                <tr>
                    <td>{{ $c['descripcion'] }}</td>
                    <td class="c muted">{{ $c['cantidad'] }}</td>
                    <td class="r muted">{{ number_format($c['unitario'], 2) }}</td>
                    <td class="r">{{ number_format($c['importe'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot">
        <tr><td class="etq">Subtotal</td><td class="r">{{ number_format($d['subtotal'], 2) }}</td></tr>
        <tr class="final"><td>Total {{ $d['moneda'] }}</td><td class="r">{{ number_format($d['total'], 2) }}</td></tr>
    </table>

    @if ($d['notas'])
        <div class="notas">{{ $d['notas'] }}</div>
    @endif

    <div class="pie">
        {{ $d['emisor']['nombre'] }}@foreach ($d['emisor']['fiscal'] as $f) &middot; {{ $f }}@endforeach
    </div>
</body>
</html>
