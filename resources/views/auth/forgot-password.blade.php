@extends('layouts.auth')

@section('title', 'Recuperar contraseña')

@section('content')
    <h4 class="card-title mb-2 fw-semibold text-center">Recuperar contraseña</h4>
    <p class="text-muted small text-center mb-4">
        Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.
    </p>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}"
                autocomplete="email"
                autofocus
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
    </form>
@endsection

@section('footer-links')
    <a href="{{ route('login') }}" class="text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
    </a>
@endsection
