@extends('layouts.auth')

@section('title', 'Restablecer contraseña')

@section('content')
    <h4 class="card-title mb-4 fw-semibold text-center">Restablecer contraseña</h4>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $email ?? '') }}"
                autocomplete="email"
                autofocus
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Nueva contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                autocomplete="new-password"
                required
            >
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary w-100">Restablecer contraseña</button>
    </form>
@endsection

@section('footer-links')
    <a href="{{ route('login') }}" class="text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
    </a>
@endsection
