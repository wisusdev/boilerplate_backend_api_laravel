<p>Hola {{ $name }},</p>
<p>Has solicitado la eliminación de tu cuenta en {{ config('app.name') }}. Si no has sido tú, por favor, ignora este mensaje.</p>
<p>Si has sido tú, por favor, haz clic en el enlace de abajo para confirmar la eliminación de tu cuenta.</p>
<p><a href="{{ $url }}" class="button">Eliminar cuenta</a></p>
<p>Saludos,<br>
{{ config('app.name') }}</p>
