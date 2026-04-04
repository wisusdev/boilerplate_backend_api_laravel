<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin') - {{ config('app.name', 'CMS') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
            --primary-color: #4f46e5;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #1e293b;
            color: #fff;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            text-decoration: none;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            padding: 0.5rem 1.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            letter-spacing: 0.05em;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 0.625rem 1.25rem;
            color: rgba(255,255,255,0.7);
            transition: all 0.15s ease;
        }

        .sidebar-nav .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }

        .sidebar-nav .nav-link.active {
            color: #fff;
            background: var(--primary-color);
        }

        .sidebar-nav .nav-link i {
            width: 1.5rem;
            font-size: 1.1rem;
        }

        /* Main content */
        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .admin-header {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-content {
            padding: 1.5rem;
        }

        /* Cards */
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 0.5rem;
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.25rem;
        }

        .card-title {
            font-weight: 600;
            margin: 0;
        }

        /* Tables */
        .table th {
            font-weight: 600;
            font-size: 0.875rem;
            color: #6b7280;
            border-bottom-width: 1px;
        }

        .table td {
            vertical-align: middle;
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

        /* Forms */
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #374151;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block !important;
            }
        }

        .sidebar-toggle {
            display: none;
        }

        /* User dropdown */
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="{{ url('/admin') }}" class="logo">
                <i class="bi bi-grid-fill me-2"></i>{{ config('app.name', 'CMS') }}
            </a>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">{{ __('Dashboard') }}</div>
            <a href="{{ url('/admin') }}" class="nav-link {{ request()->is('admin') ? 'active' : '' }}">
                <i class="bi bi-speedometer2 me-2"></i> {{ __('Dashboard') }}
            </a>

            <div class="nav-section mt-3">{{ __('Content') }}</div>
            <a href="{{ route('admin.pages.index') }}" class="nav-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text me-2"></i> {{ __('Pages') }}
            </a>
            <a href="{{ route('admin.blog.posts.index') }}" class="nav-link {{ request()->routeIs('admin.blog.posts.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text me-2"></i> {{ __('Posts') }}
            </a>
            <a href="{{ route('admin.blog.categories.index') }}" class="nav-link {{ request()->routeIs('admin.blog.categories.*') ? 'active' : '' }}">
                <i class="bi bi-folder me-2"></i> {{ __('Categories') }}
            </a>
            <a href="{{ route('admin.media.index') }}" class="nav-link {{ request()->routeIs('admin.media.*') ? 'active' : '' }}">
                <i class="bi bi-images me-2"></i> {{ __('Media') }}
            </a>

            <div class="nav-section mt-3">{{ __('Appearance') }}</div>
            <a href="{{ route('admin.menus.index') }}" class="nav-link {{ request()->routeIs('admin.menus.*') ? 'active' : '' }}">
                <i class="bi bi-list-ul me-2"></i> {{ __('Menus') }}
            </a>
            <a href="{{ route('admin.themes.index') }}" class="nav-link {{ request()->routeIs('admin.themes.*') ? 'active' : '' }}">
                <i class="bi bi-palette me-2"></i> {{ __('Themes') }}
            </a>

            <div class="nav-section mt-3">{{ __('Users') }}</div>
            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="bi bi-people me-2"></i> {{ __('Users') }}
            </a>
            <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                <i class="bi bi-shield-lock me-2"></i> {{ __('Roles') }}
            </a>

            <div class="nav-section mt-3">{{ __('System') }}</div>
            <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <i class="bi bi-gear me-2"></i> {{ __('Settings') }}
            </a>
            <a href="{{ route('admin.modules.index') }}" class="nav-link {{ request()->routeIs('admin.modules.*') ? 'active' : '' }}">
                <i class="bi bi-puzzle me-2"></i> {{ __('Modules') }}
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-link sidebar-toggle me-2" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <span class="text-muted d-none d-md-inline">@yield('title', 'Dashboard')</span>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- View Site -->
                <a href="{{ url('/') }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> {{ __('View Site') }}
                </a>

                <!-- User Dropdown -->
                <div class="dropdown user-dropdown">
                    <button class="btn btn-link dropdown-toggle text-dark text-decoration-none" type="button" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=4f46e5&color=fff" 
                             alt="Avatar" 
                             class="rounded-circle" 
                             width="32" 
                             height="32">
                        <span class="ms-2 d-none d-md-inline">{{ auth()->user()->name ?? 'Admin' }}</span>
                        <i class="bi bi-chevron-down ms-1 small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><i class="bi bi-person me-2"></i> {{ __('Profile') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i> {{ __('Logout') }}
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="admin-content">
            @yield('content')
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (window.innerWidth < 992 && 
                !sidebar.contains(e.target) && 
                !toggle.contains(e.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
