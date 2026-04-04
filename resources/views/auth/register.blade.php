@extends('layouts.auth')

@section('title', 'Crear cuenta')

@section('content')
    <h4 class="card-title mb-4 fw-semibold text-center">Crear cuenta</h4>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-6">
                <label for="first_name" class="form-label">Nombre</label>
                <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    class="form-control @error('first_name') is-invalid @enderror"
                    value="{{ old('first_name') }}"
                    autocomplete="given-name"
                    autofocus
                    required
                >
                @error('first_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6">
                <label for="last_name" class="form-label">Apellido</label>
                <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    class="form-control @error('last_name') is-invalid @enderror"
                    value="{{ old('last_name') }}"
                    autocomplete="family-name"
                    required
                >
                @error('last_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="username" class="form-label">Usuario</label>
            <input
                type="text"
                id="username"
                name="username"
                class="form-control @error('username') is-invalid @enderror"
                value="{{ old('username') }}"
                autocomplete="username"
                required
            >
            @error('username')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}"
                autocomplete="email"
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
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

        <button type="submit" class="btn btn-primary w-100">Registrarse</button>
    </form>
@endsection

@section('footer-links')
    ¿Ya tienes cuenta?
    <a href="{{ route('login') }}" class="text-decoration-none">Inicia sesión</a>
@endsection
