@extends('layouts.email')
@section('content')

<p>{{ $emisor }} te envía tu documento tributario electrónico.</p>
<table cellpadding="4" style="border-collapse:collapse; font-size:14px">
    <tr><td><b>Documento</b></td><td>{{ $documento }}</td></tr>
    <tr><td><b>Número de control</b></td><td>{{ $numeroControl }}</td></tr>
    <tr><td><b>Código de generación</b></td><td>{{ $codigoGeneracion }}</td></tr>
    <tr><td><b>Total</b></td><td>${{ $total }}</td></tr>
</table>
@if ($contingencia)
    <p>Documento emitido en contingencia: el Ministerio de Hacienda no estaba disponible. Es válido y recibirá su sello de
        recepción en cuanto se restablezca el servicio; te enviaremos la versión con sello.</p>
@else
    <p>Documento con sello de recepción del Ministerio de Hacienda: <b>{{ $sello }}</b>.</p>
@endif
<p>Adjuntamos la representación gráfica (PDF) y el archivo del DTE (JSON), que es el documento con validez tributaria.</p>
<p>Saludos,<br>
{{ $emisor }}</p>
@endsection
