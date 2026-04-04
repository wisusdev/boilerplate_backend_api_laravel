<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Auth') — {{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }
        .auth-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="container py-5">
        <div class="auth-card">
            <div class="text-center mb-4">
                <a href="{{ url('/') }}" class="auth-logo text-decoration-none">
                    <i class="bi bi-layers-fill text-primary me-1"></i>
                    {{ config('app.name') }}
                </a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>

            <div class="text-center mt-3 small text-muted">
                @yield('footer-links')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
