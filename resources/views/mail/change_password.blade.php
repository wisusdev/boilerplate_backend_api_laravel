@extends('layouts.email')
@section('content')

<p>Hola {{ $name }}, para cambiar tu contraseña, haz clic en el siguiente enlace:</p>
<p><a href="{{ $url }}">{{ $url }}</a></p>
<p>Si no has solicitado este cambio, ignora este mensaje.</p>
<p>Saludos,<br>
{{ config('app.name') }}</p>
