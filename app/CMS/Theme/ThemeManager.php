<?php

namespace App\CMS\Theme;

use App\CMS\Theme\Contracts\ThemeInterface;
use App\CMS\Theme\Contracts\ThemeManagerInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;

class ThemeManager implements ThemeManagerInterface
{
    protected ?ThemeInterface $activeTheme = null;
    protected ?ThemeInterface $parentTheme = null;
    protected array $themes = [];
    protected bool $themesLoaded = false;
    protected Filesystem $filesystem;
    protected ViewFactory $viewFactory;

    public function __construct(Filesystem $filesystem, ViewFactory $viewFactory)
    {
        $this->filesystem = $filesystem;
        $this->viewFactory = $viewFactory;
    }

    /**
     * Set the active theme.
     */
    public function set(string $theme): void
    {
        if (!$this->exists($theme)) {
            throw new \InvalidArgumentException("Theme [{$theme}] not found.");
        }

        $this->activeTheme = $this->find($theme);

        // Load parent theme if exists
        if (config('theme.parent_theme_support') && $parentName = $this->activeTheme->getParent()) {
            $this->parentTheme = $this->find($parentName);
        } else {
            $this->parentTheme = null;
        }

        // Register view paths
        $this->registerViewPaths();
    }

    /**
     * Get the active theme.
     */
    public function active(): ?ThemeInterface
    {
        return $this->activeTheme;
    }

    /**
     * Get the active theme name.
     */
    public function getActive(): ?string
    {
        return $this->activeTheme?->getSlug();
    }

    /**
     * Get the parent theme if exists.
     */
    public function parent(): ?ThemeInterface
    {
        return $this->parentTheme;
    }

    /**
     * Get all available themes.
     */
    public function all(): array
    {
        $this->loadThemes();

        return $this->themes;
    }

    /**
     * Get a theme by name.
     */
    public function find(string $theme): ?ThemeInterface
    {
        $this->loadThemes();

        return $this->themes[$theme] ?? null;
    }

    /**
     * Check if a theme exists.
     */
    public function exists(string $theme): bool
    {
        return $this->find($theme) !== null;
    }

    /**
     * Clear the active theme.
     */
    public function clear(): void
    {
        $this->activeTheme = null;
        $this->parentTheme = null;

        // Reset view paths to default
        $this->resetViewPaths();
    }

    /**
     * Get the theme path.
     */
    public function path(string $path = '', ?string $theme = null): string
    {
        $themePath = config('theme.path');
        $themeName = $theme ?? $this->getActive() ?? config('theme.default');

        $fullPath = $themePath . '/' . $themeName;

        if ($path) {
            $fullPath .= '/' . ltrim($path, '/');
        }

        return $fullPath;
    }

    /**
     * Get view paths for the finder.
     */
    public function getViewPaths(): array
    {
        $paths = [];

        // Active theme views first
        if ($this->activeTheme) {
            $paths[] = $this->activeTheme->getViewsPath();
        }

        // Parent theme views
        if ($this->parentTheme) {
            $paths[] = $this->parentTheme->getViewsPath();
        }

        // Default Laravel views
        $paths[] = resource_path('views');

        return $paths;
    }

    /**
     * Get the asset URL for a theme asset.
     */
    public function asset(string $path, ?string $theme = null): string
    {
        $themeName = $theme ?? $this->getActive() ?? config('theme.default');
        $publicDir = config('theme.public_assets_dir', 'themes');

        return asset($publicDir . '/' . $themeName . '/' . ltrim($path, '/'));
    }

    /**
     * Load all themes from the themes directory.
     */
    protected function loadThemes(): void
    {
        if ($this->themesLoaded) {
            return;
        }

        $cacheKey = 'cms.themes.all';

        if (config('theme.cache') && Cache::has($cacheKey)) {
            $this->themes = Cache::get($cacheKey);
            $this->themesLoaded = true;
            return;
        }

        $themesPath = config('theme.path');

        if (!$this->filesystem->isDirectory($themesPath)) {
            $this->themesLoaded = true;
            return;
        }

        $directories = $this->filesystem->directories($themesPath);

        foreach ($directories as $directory) {
            $theme = Theme::fromPath($directory);

            if ($theme) {
                $this->themes[$theme->getSlug()] = $theme;
            }
        }

        if (config('theme.cache')) {
            Cache::put($cacheKey, $this->themes, now()->addDay());
        }

        $this->themesLoaded = true;
    }

    /**
     * Register view paths with the view finder.
     */
    protected function registerViewPaths(): void
    {
        $finder = $this->viewFactory->getFinder();
        $viewPaths = $this->getViewPaths();

        // Prepend theme paths to the finder
        foreach (array_reverse($viewPaths) as $path) {
            if ($this->filesystem->isDirectory($path)) {
                $finder->prependLocation($path);
            }
        }
    }

    /**
     * Reset view paths to default.
     */
    protected function resetViewPaths(): void
    {
        $finder = $this->viewFactory->getFinder();

        // Flush all cached views
        $finder->flush();
    }

    /**
     * Publish theme assets to the public directory.
     */
    public function publishAssets(?string $theme = null): bool
    {
        $themeName = $theme ?? $this->getActive();

        if (!$themeName) {
            return false;
        }

        $themeInstance = $this->find($themeName);

        if (!$themeInstance) {
            return false;
        }

        $source = $themeInstance->getAssetsPath();
        $destination = public_path(config('theme.public_assets_dir') . '/' . $themeName);

        if (!$this->filesystem->isDirectory($source)) {
            return false;
        }

        $this->filesystem->copyDirectory($source, $destination);

        return true;
    }

    /**
     * Clear the themes cache.
     */
    public function clearCache(): void
    {
        Cache::forget('cms.themes.all');
        $this->themes = [];
        $this->themesLoaded = false;
    }
}
