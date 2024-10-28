@extends('layouts.email')
@section('content')
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="main">
        <tr>
            <td class="wrapper">
                <p>Hola <b>{{ $name }}</b>, para restablecer tu contraseña, haz clic en el siguiente enlace:</p>
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary">
                    <tbody>
                        <tr>
                            <td align="left">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tbody>
                                        <tr>
                                            <td><a href="{{ $url }}" target="_blank">Resetear mi contraseña</a></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p>También puedes copiar y pega la URL de abajo en tu navegador web: <a href="{{ $url }}">{{ $url }}</a></p>
                <p>Si no has solicitado este cambio, ignora este mensaje.</p>
            </td>
        </tr>
    </table>
@endsection
