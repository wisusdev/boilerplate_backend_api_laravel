@php
    $m = fn ($v) => '$'.number_format((float) $v, 2, '.', ',');
    $o = fn ($v) => ($v === null || trim((string) $v) === '') ? '-' : $v;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        /* Representación gráfica del DTE: sigue la sugerida por el Manual Funcional. */
        @page { margin: 26px 30px 34px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #111827; font-size: 8.5px; line-height: 1.35; }
        .c { text-align: center; } .r { text-align: right; }
        h1 { font-size: 12px; margin: 0; text-align: center; }
        h2 { font-size: 11px; margin: 2px 0 0; text-align: center; }
        .ver { text-align: center; font-size: 7px; color: #4b5563; }
        .banda { background: #fde7c8; color: #7c2d12; font-weight: bold; text-align: center; padding: 3px; margin-top: 4px; }
        .anulado { background: #e5e7eb; color: #111827; font-weight: bold; text-align: center; padding: 3px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        .ident { margin-top: 6px; }
        .ident td { padding: 1.5px 0; vertical-align: top; }
        .ident .k { width: 30%; font-weight: bold; }
        .sec { background: #e5e7eb; font-weight: bold; padding: 3px 5px; margin-top: 7px; }
        .partes td.col { width: 50%; vertical-align: top; padding-right: 6px; }
        .kv td { padding: 1px 0; vertical-align: top; }
        .kv .k { width: 32%; color: #374151; }
        .items { margin-top: 2px; }
        .items th { background: #f3f4f6; border: 1px solid #d1d5db; font-size: 7px; padding: 3px 2px; }
        .items td { border: 1px solid #d1d5db; padding: 3px 2px; vertical-align: top; }
        .tot td { padding: 2px 5px; }
        .tot .b td { font-weight: bold; border-top: 1px solid #9ca3af; }
        .qr { width: 96px; }
        .pie { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 7px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="pie">Representación gráfica del DTE. El documento con validez tributaria es el archivo JSON firmado.</div>

    <h1>DOCUMENTO TRIBUTARIO ELECTRÓNICO</h1>
    <h2>{{ $d['titulo'] }}</h2>
    <div class="ver">Ver. {{ $d['version'] }}</div>
    @if ($d['pruebas'])
        <div class="banda">AMBIENTE DE PRUEBAS — SIN VALIDEZ TRIBUTARIA</div>
    @endif
    @if ($d['invalidado'])
        <div class="anulado">DOCUMENTO INVALIDADO ANTE EL MINISTERIO DE HACIENDA</div>
    @endif

    <table class="ident">
        <tr>
            <td>
                <table>
                    @foreach ($d['identificacion'] as $k => $v)
                        <tr><td class="k">{{ $k }}:</td><td>{{ $v }}</td></tr>
                    @endforeach
                </table>
            </td>
            @if ($d['qr'])
                <td class="c" style="width:110px">
                    <img src="{{ $d['qr'] }}" class="qr" alt="">
                    <div style="font-size:7px">Consulta pública MH</div>
                </td>
            @endif
        </tr>
    </table>

    <table class="partes">
        <tr>
            <td class="col">
                <div class="sec">EMISOR</div>
                <table class="kv">
                    @foreach ($d['emisor'] as $k => $v)
                        <tr><td class="k">{{ $k }}:</td><td>{{ $o($v) }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td class="col">
                <div class="sec">RECEPTOR</div>
                <table class="kv">
                    @forelse ($d['receptor'] ?? [] as $k => $v)
                        <tr><td class="k">{{ $k }}:</td><td>{{ $o($v) }}</td></tr>
                    @empty
                        <tr><td>Consumidor final</td></tr>
                    @endforelse
                </table>
            </td>
        </tr>
    </table>

    <div class="sec">DOCUMENTOS RELACIONADOS</div>
    @forelse ($d['relacionados'] as $r)
        <div>Tipo: {{ $r['tipoDocumento'] ?? '-' }} · N°: {{ $r['numeroDocumento'] ?? '-' }} · Fecha: {{ $r['fechaEmision'] ?? '-' }}</div>
    @empty
        <div>-</div>
    @endforelse

    <div class="sec">VENTA POR CUENTA DE TERCEROS</div>
    <div>{{ $d['ventaTercero'] ? 'NIT: '.($d['ventaTercero']['nit'] ?? '-').' · Nombre: '.($d['ventaTercero']['nombre'] ?? '-') : '-' }}</div>

    <div class="sec">OTROS DOCUMENTOS ASOCIADOS</div>
    @forelse ($d['otrosDocumentos'] as $od)
        <div>{{ $od['descDocumento'] ?? '' }} {{ $od['detalleDocumento'] ?? '' }}</div>
    @empty
        <div>-</div>
    @endforelse

    <div class="sec">CUERPO DEL DOCUMENTO</div>
    <table class="items">
        <thead>
            <tr>
                <th>N°</th><th>Cant.</th><th>Unidad</th><th style="width:30%">Descripción</th>
                <th>Precio unit.</th><th>Descuento</th><th>Otros montos no afectos</th>
                <th>Ventas no sujetas</th><th>Ventas exentas</th><th>Ventas gravadas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['items'] as $it)
                <tr>
                    <td class="c">{{ $it['num'] }}</td>
                    <td class="r">{{ $it['cantidad'] }}</td>
                    <td>{{ $it['unidad'] }}</td>
                    <td>{{ $it['descripcion'] }}</td>
                    <td class="r">{{ $m($it['precio']) }}</td>
                    <td class="r">{{ $m($it['descuento']) }}</td>
                    <td class="r">{{ $m($it['noGravado']) }}</td>
                    <td class="r">{{ $m($it['noSujeta']) }}</td>
                    <td class="r">{{ $m($it['exenta']) }}</td>
                    <td class="r">{{ $m($it['gravada']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top:6px">
        <tr>
            <td style="width:55%; vertical-align:top; padding-right:10px">
                <div class="sec">VALOR EN LETRAS</div>
                <div>{{ $o($d['letras']) }}</div>
                <div class="sec">CONDICIÓN DE LA OPERACIÓN</div>
                <div>{{ $d['condicion'] }}</div>
                <div class="sec">FORMAS DE PAGO</div>
                @forelse ($d['pagos'] as $p)
                    <div>{{ $p['forma'] }}: {{ $m($p['monto']) }}</div>
                @empty
                    <div>-</div>
                @endforelse
                <div class="sec">OBSERVACIONES</div>
                <div>{{ $o($d['observaciones']) }}</div>
                @foreach ($d['apendice'] as $a)
                    <div style="margin-top:3px">{{ $a['etiqueta'] ?? '' }}: {{ $a['valor'] ?? '' }}</div>
                @endforeach
            </td>
            <td style="vertical-align:top">
                <div class="sec">RESUMEN</div>
                <table class="tot">
                    @foreach ($d['totales'] as [$k, $v, $b])
                        <tr @class(['b' => $b])><td>{{ $k }}</td><td class="r">{{ $m($v) }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
