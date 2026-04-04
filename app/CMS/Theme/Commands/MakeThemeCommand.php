<?php

namespace App\CMS\Theme\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeThemeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:theme
                            {name : The name of the theme}
                            {--parent= : The parent theme to extend}
                            {--description= : The theme description}
                            {--author= : The theme author}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new theme';

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = Str::slug($name);
        $themesPath = config('theme.path');
        $themePath = $themesPath . '/' . $slug;

        if ($this->filesystem->isDirectory($themePath)) {
            $this->error("Theme [{$name}] already exists!");
            return self::FAILURE;
        }

        $this->info("Creating theme: {$name}");

        // Create theme directories
        $directories = [
            $themePath,
            $themePath . '/views',
            $themePath . '/views/layouts',
            $themePath . '/views/partials',
            $themePath . '/views/components',
            $themePath . '/views/pages',
            $themePath . '/views/posts',
            $themePath . '/assets',
            $themePath . '/assets/css',
            $themePath . '/assets/js',
            $themePath . '/assets/images',
        ];

        foreach ($directories as $directory) {
            $this->filesystem->makeDirectory($directory, 0755, true, true);
        }

        // Create theme.json
        $this->createThemeConfig($themePath, $name, $slug);

        // Create default layout
        $this->createDefaultLayout($themePath);

        // Create default CSS
        $this->createDefaultCss($themePath);

        // Create Vite config
        $this->createViteConfig($themePath, $slug);

        // Create package.json
        $this->createPackageJson($themePath, $slug);

        $this->info("Theme [{$name}] created successfully!");
        $this->newLine();
        $this->line("Theme location: {$themePath}");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("  1. cd themes/{$slug}");
        $this->line("  2. npm install");
        $this->line("  3. npm run dev");

        return self::SUCCESS;
    }

    /**
     * Create the theme configuration file.
     */
    protected function createThemeConfig(string $path, string $name, string $slug): void
    {
        $config = [
            'name' => $name,
            'slug' => $slug,
            'version' => '1.0.0',
            'description' => $this->option('description') ?: "A custom theme for the CMS",
            'author' => $this->option('author') ?: 'CMS Team',
            'parent' => $this->option('parent'),
            'supports' => [
                'menus',
                'widgets',
                'custom-header',
                'custom-footer',
                'post-thumbnails',
            ],
            'locations' => [
                'primary' => 'Primary Navigation',
                'footer' => 'Footer Navigation',
                'social' => 'Social Links',
            ],
            'layouts' => [
                'default' => 'Default Layout',
                'full-width' => 'Full Width',
                'sidebar-left' => 'Sidebar Left',
                'sidebar-right' => 'Sidebar Right',
            ],
            'templates' => [
                'page' => [
                    'default' => 'Default Page',
                    'contact' => 'Contact Page',
                    'landing' => 'Landing Page',
                ],
                'post' => [
                    'default' => 'Default Post',
                    'featured' => 'Featured Post',
                ],
            ],
            'settings' => [
                'primary_color' => '#3b82f6',
                'secondary_color' => '#6366f1',
                'font_family' => 'Inter, sans-serif',
            ],
        ];

        $this->filesystem->put(
            $path . '/theme.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Create the default layout file.
     */
    protected function createDefaultLayout(string $path): void
    {
        $layout = <<<'BLADE'
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'CMS'))</title>

    <!-- SEO Meta Tags -->
    @stack('meta')

    <!-- Styles -->
    @vite(['assets/css/app.css', 'assets/js/app.js'], $theme ?? 'default')
    @stack('styles')
</head>
<body class="antialiased">
    <!-- Header -->
    @include('partials.header')

    <!-- Main Content -->
    <main class="min-h-screen">
        @yield('content')
    </main>

    <!-- Footer -->
    @include('partials.footer')

    <!-- Scripts -->
    @stack('scripts')
</body>
</html>
BLADE;

        $this->filesystem->put($path . '/views/layouts/app.blade.php', $layout);
    }

    /**
     * Create default partials.
     */
    protected function createDefaultPartials(string $path): void
    {
        // Header
        $header = <<<'BLADE'
<header class="bg-white shadow">
    <nav class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-xl font-bold">
                {{ config('app.name', 'CMS') }}
            </a>

            <!-- Navigation Menu -->
            <div class="hidden md:flex space-x-4">
                @cms_menu('primary')
            </div>

            <!-- Mobile Menu Button -->
            <button class="md:hidden" id="mobile-menu-button">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </nav>
</header>
BLADE;

        $this->filesystem->put($path . '/views/partials/header.blade.php', $header);

        // Footer
        $footer = <<<'BLADE'
<footer class="bg-gray-800 text-white">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- About -->
            <div>
                <h3 class="text-lg font-semibold mb-4">{{ config('app.name', 'CMS') }}</h3>
                <p class="text-gray-400">
                    A modern content management system built with Laravel.
                </p>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                @cms_menu('footer')
            </div>

            <!-- Contact -->
            <div>
                <h3 class="text-lg font-semibold mb-4">Contact</h3>
                <p class="text-gray-400">info@example.com</p>
            </div>

            <!-- Social -->
            <div>
                <h3 class="text-lg font-semibold mb-4">Follow Us</h3>
                @cms_menu('social')
            </div>
        </div>

        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'CMS') }}. All rights reserved.</p>
        </div>
    </div>
</footer>
BLADE;

        $this->filesystem->put($path . '/views/partials/footer.blade.php', $footer);
    }

    /**
     * Create default CSS file.
     */
    protected function createDefaultCss(string $path): void
    {
        $css = <<<'CSS'
@import 'tailwindcss';

/* Theme Variables */
:root {
    --color-primary: theme('colors.blue.500');
    --color-secondary: theme('colors.indigo.500');
    --font-family: 'Inter', sans-serif;
}

/* Base Styles */
body {
    font-family: var(--font-family);
}

/* Custom Components */
.btn-primary {
    @apply bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition;
}

.btn-secondary {
    @apply bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300 transition;
}

/* Cards */
.card {
    @apply bg-white rounded-lg shadow p-6;
}

/* Forms */
.form-input {
    @apply w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500;
}

.form-label {
    @apply block text-sm font-medium text-gray-700 mb-1;
}
CSS;

        $this->filesystem->put($path . '/assets/css/app.css', $css);

        // Create default JS
        $js = <<<'JS'
// Theme JavaScript

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
});

// Add any theme-specific JavaScript here
console.log('Theme loaded');
JS;

        $this->filesystem->put($path . '/assets/js/app.js', $js);

        // Create partials
        $this->createDefaultPartials($path);
    }

    /**
     * Create Vite configuration file.
     */
    protected function createViteConfig(string $path, string $slug): void
    {
        $viteConfig = <<<JS
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'assets/css/app.css',
                'assets/js/app.js',
            ],
            buildDirectory: 'build/{$slug}',
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'assets'),
        },
    },
    build: {
        outDir: '../../public/themes/{$slug}',
        emptyOutDir: true,
        manifest: true,
    },
});
JS;

        $this->filesystem->put($path . '/vite.config.js', $viteConfig);
    }

    /**
     * Create package.json file.
     */
    protected function createPackageJson(string $path, string $slug): void
    {
        $packageJson = [
            'name' => "theme-{$slug}",
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'dev' => 'vite',
                'build' => 'vite build',
            ],
            'devDependencies' => [
                'autoprefixer' => '^10.4.19',
                'laravel-vite-plugin' => '^1.0.0',
                'postcss' => '^8.4.38',
                'tailwindcss' => '^4.0.0',
                'vite' => '^5.0.0',
            ],
        ];

        $this->filesystem->put(
            $path . '/package.json',
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Create tailwind config
        $tailwindConfig = <<<'JS'
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './views/**/*.blade.php',
        './assets/**/*.js',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
JS;

        $this->filesystem->put($path . '/tailwind.config.js', $tailwindConfig);

        // Create postcss config
        $postcssConfig = <<<'JS'
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
JS;

        $this->filesystem->put($path . '/postcss.config.js', $postcssConfig);
    }
}
