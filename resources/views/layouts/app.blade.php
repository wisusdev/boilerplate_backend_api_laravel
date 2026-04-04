<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'CMS'))</title>
    <meta name="description" content="@yield('meta_description', '')">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #6366f1;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }

        /* Header */
        .site-header {
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .nav-link {
            font-weight: 500;
            color: #374151 !important;
            transition: color 0.15s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        /* Footer */
        .site-footer {
            background: #1e293b;
            color: #94a3b8;
            padding: 3rem 0 1.5rem;
        }

        .site-footer a {
            color: #cbd5e1;
            text-decoration: none;
        }

        .site-footer a:hover {
            color: #fff;
        }

        .footer-title {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1.5rem;
            margin-top: 2rem;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            padding: 4rem 0;
        }

        /* Cards */
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 0.5rem;
            transition: box-shadow 0.15s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'CMS') }}
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarMain">
                    @php
                        $primaryMenu = \Modules\Menu\Models\Menu::where('location', 'primary')->first();
                    @endphp

                    @if($primaryMenu)
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            @foreach($primaryMenu->getTree() as $item)
                                @if(empty($item['children']))
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ $item['url'] ?? '#' }}" 
                                           @if($item['open_in_new_tab'] ?? false) target="_blank" @endif>
                                            @if($item['icon'] ?? false)
                                                <i class="bi bi-{{ $item['icon'] }} me-1"></i>
                                            @endif
                                            {{ $item['title'] }}
                                        </a>
                                    </li>
                                @else
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                            {{ $item['title'] }}
                                        </a>
                                        <ul class="dropdown-menu">
                                            @foreach($item['children'] as $child)
                                                <li>
                                                    <a class="dropdown-item" href="{{ $child['url'] ?? '#' }}">
                                                        {{ $child['title'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @else
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('/') }}">{{ __('Home') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ url('/blog') }}">{{ __('Blog') }}</a>
                            </li>
                        </ul>
                    @endif

                    <ul class="navbar-nav">
                        @auth
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    {{ auth()->user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @can('access-admin')
                                        <li><a class="dropdown-item" href="{{ url('/admin') }}">{{ __('Admin') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                    @endcan
                                    <li>
                                        <form action="{{ route('logout') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item">{{ __('Logout') }}</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="footer-title">{{ config('app.name', 'CMS') }}</h5>
                    <p class="mb-0">{{ __('A modern content management system built with Laravel.') }}</p>
                </div>

                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="footer-title">{{ __('Quick Links') }}</h6>
                    <ul class="footer-links">
                        <li><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
                        <li><a href="{{ url('/blog') }}">{{ __('Blog') }}</a></li>
                        <li><a href="{{ url('/contact') }}">{{ __('Contact') }}</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="footer-title">{{ __('Legal') }}</h6>
                    <ul class="footer-links">
                        <li><a href="{{ url('/privacy') }}">{{ __('Privacy Policy') }}</a></li>
                        <li><a href="{{ url('/terms') }}">{{ __('Terms of Service') }}</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-4 mb-4">
                    <h6 class="footer-title">{{ __('Follow Us') }}</h6>
                    <div class="d-flex gap-3">
                        <a href="#" class="fs-4"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="fs-4"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="fs-4"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="fs-4"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom text-center">
                <p class="mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'CMS') }}. {{ __('All rights reserved.') }}</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>
</html>
