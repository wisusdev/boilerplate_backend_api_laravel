<p>Hola {{ $name }},</p>
<p>Para restablecer tu contraseña, haz clic en el siguiente enlace:</p>
<p><a href="{{ $url }}">{{ $url }}</a></p>
<p>O, copia y pega la URL de abajo en tu navegador web: <a href="{{$url}}">{{$url}}</a></p>
<p>Si no has solicitado este cambio, ignora este mensaje.</p>
<p>Saludos,<br>
{{ config('app.name') }}</p>
