<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        /* Moderna: bloque de color a sangre, tipografía grande, sin rejilla. */
        @page { margin: 0; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #111827; font-size: 11px; line-height: 1.5; }
        .r { text-align: right; } .c { text-align: center; }
        .muted { color: #9ca3af; }

        .banda { background: #0f172a; color: #fff; padding: 30px 44px 26px; }
        .banda table { width: 100%; border-collapse: collapse; }
        .banda td { vertical-align: top; color: #fff; }
        .logo { max-height: 40px; max-width: 170px; }
        .marca { font-size: 17px; font-weight: bold; letter-spacing: .3px; }
        .banda .linea { color: #94a3b8; font-size: 9.5px; }
        .tipo { font-size: 30px; font-weight: bold; letter-spacing: -.5px; line-height: 1; }
        .num { color: #38bdf8; font-size: 12px; font-weight: bold; margin-top: 4px; }

        .cuerpo { padding: 26px 44px 0; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .meta td { vertical-align: top; width: 33.33%; padding-right: 16px; }
        .meta h2 { font-size: 8.5px; margin: 0 0 5px; color: #9ca3af;
                   text-transform: uppercase; letter-spacing: 1px; }
        .meta .dato { font-weight: bold; font-size: 12px; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items th { font-size: 8.5px; color: #9ca3af; padding: 0 0 8px; text-align: left;
                         text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #0f172a; }
        table.items td { padding: 11px 0; border-bottom: 1px solid #f1f5f9; }
        table.items .desc { font-weight: bold; font-size: 12px; }

        .tot { width: 44%; float: right; border-collapse: collapse; margin-top: 14px; }
        .tot td { padding: 6px 0; }
        .tot .final td { border-top: 2px solid #0f172a; padding-top: 10px;
                         font-size: 17px; font-weight: bold; }
        .notas { clear: both; padding-top: 30px; color: #6b7280; font-size: 10px; }
        .pie { margin: 24px 44px 0; border-top: 1px solid #f1f5f9; padding-top: 10px;
               color: #cbd5e1; font-size: 9px; }
    </style>
</head>
<body>
    <div class="banda">
        <table>
            <tr>
                <td style="width:60%">
                    @if ($d['emisor']['logo'])
                        <img src="{{ $d['emisor']['logo'] }}" class="logo" alt="">
                    @else
                        <div class="marca">{{ $d['emisor']['nombre'] }}</div>
                    @endif
                    @foreach (array_merge($d['emisor']['fiscal'], $d['emisor']['lineas']) as $l)
                        <div class="linea">{{ $l }}</div>
                    @endforeach
                </td>
                <td class="r">
                    <div class="tipo">Factura</div>
                    <div class="num">{{ $d['numero'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="cuerpo">
        <table class="meta">
            <tr>
                <td>
                    <h2>Facturar a</h2>
                    <div class="dato">{{ $d['cliente']['nombre'] }}</div>
                    @if ($d['cliente']['documento'])<div class="muted">{{ $d['cliente']['documento'] }}</div>@endif
                    @if ($d['cliente']['correo'])<div class="muted">{{ $d['cliente']['correo'] }}</div>@endif
                </td>
                <td>
                    <h2>Emitida</h2>
                    <div class="dato">{{ $d['emitido']?->format('d/m/Y') }}</div>
                    @if ($d['reserva'])<div class="muted">Reserva #{{ $d['reserva'] }}</div>@endif
                    @if ($d['dteNumero'])<div class="muted">DTE {{ $d['dteNumero'] }}</div>@endif
                </td>
                <td>
                    <h2>Total</h2>
                    <div class="dato">{{ $d['moneda'] }} {{ number_format($d['total'], 2) }}</div>
                    <div class="muted">{{ $d['estadoEtiqueta'] }}</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:56%">Concepto</th>
                    <th class="c" style="width:10%">Cant.</th>
                    <th class="r" style="width:17%">Precio</th>
                    <th class="r" style="width:17%">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($d['conceptos'] as $c)
                    <tr>
                        <td class="desc">{{ $c['descripcion'] }}</td>
                        <td class="c">{{ $c['cantidad'] }}</td>
                        <td class="r muted">{{ number_format($c['unitario'], 2) }}</td>
                        <td class="r">{{ $d['moneda'] }} {{ number_format($c['importe'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="tot">
            <tr><td class="muted">Subtotal</td><td class="r">{{ $d['moneda'] }} {{ number_format($d['subtotal'], 2) }}</td></tr>
            <tr class="final"><td>Total</td><td class="r">{{ $d['moneda'] }} {{ number_format($d['total'], 2) }}</td></tr>
        </table>

        @if ($d['notas'])
            <div class="notas">{{ $d['notas'] }}</div>
        @endif
    </div>

    <div class="pie">{{ $d['emisor']['nombre'] }}</div>
</body>
</html>
