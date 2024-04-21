<h2 class="mb-4">Hola {{ $name }},</h2>
<p class="m-0">Por favor, haz clic en el enlace de abajo para verificar tu dirección de correo electrónico.</p>
<p class="m-0"><a href="{{ $url }}" class="button">Verificar correo electrónico</a></p>
<p class="mb-3">Si tienes problemas para hacer clic en el botón "Verificar correo electrónico", copia y pega la URL de abajo en tu navegador web:</p>
<p><a href="{{ $url }}">{{ $url }}</a></p>
<p class="mt-4">Si no has solicitado este cambio, ignora este mensaje.</p>
<p>Saludos,<br>
{{ config('app.name') }}</p>
