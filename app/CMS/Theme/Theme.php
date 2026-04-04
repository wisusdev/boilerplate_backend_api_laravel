<?php

namespace App\CMS\Theme;

use App\CMS\Theme\Contracts\ThemeInterface;
use Illuminate\Support\Arr;

class Theme implements ThemeInterface
{
    protected array $config;
    protected string $path;

    public function __construct(string $path, array $config = [])
    {
        $this->path = $path;
        $this->config = $config;
    }

    /**
     * Create a theme instance from a path.
     */
    public static function fromPath(string $path): ?self
    {
        $configFile = $path . '/' . config('theme.config_file', 'theme.json');

        if (!file_exists($configFile)) {
            return null;
        }

        $config = json_decode(file_get_contents($configFile), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return new self($path, $config);
    }

    /**
     * Get the theme name.
     */
    public function getName(): string
    {
        return $this->config['name'] ?? basename($this->path);
    }

    /**
     * Get the theme slug.
     */
    public function getSlug(): string
    {
        return $this->config['slug'] ?? basename($this->path);
    }

    /**
     * Get the theme version.
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * Get the theme description.
     */
    public function getDescription(): string
    {
        return $this->config['description'] ?? '';
    }

    /**
     * Get the theme author.
     */
    public function getAuthor(): string
    {
        return $this->config['author'] ?? '';
    }

    /**
     * Get the parent theme name if exists.
     */
    public function getParent(): ?string
    {
        return $this->config['parent'] ?? null;
    }

    /**
     * Get the theme path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the views path.
     */
    public function getViewsPath(): string
    {
        return $this->path . '/' . config('theme.views_dir', 'views');
    }

    /**
     * Get the assets path.
     */
    public function getAssetsPath(): string
    {
        return $this->path . '/' . config('theme.assets_dir', 'assets');
    }

    /**
     * Get theme configuration.
     */
    public function getConfig(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return Arr::get($this->config, $key, $default);
    }

    /**
     * Get theme screenshot URL.
     */
    public function getScreenshot(): ?string
    {
        $screenshots = ['screenshot.png', 'screenshot.jpg', 'screenshot.webp'];

        foreach ($screenshots as $screenshot) {
            if (file_exists($this->path . '/' . $screenshot)) {
                return asset('themes/' . $this->getSlug() . '/' . $screenshot);
            }
        }

        return null;
    }

    /**
     * Check if theme supports a feature.
     */
    public function supports(string $feature): bool
    {
        $supports = $this->config['supports'] ?? [];

        return in_array($feature, $supports, true);
    }

    /**
     * Get theme settings.
     */
    public function getSettings(): array
    {
        return $this->config['settings'] ?? [];
    }

    /**
     * Get theme locations.
     */
    public function getLocations(): array
    {
        return $this->config['locations'] ?? config('theme.locations', []);
    }

    /**
     * Get theme layouts.
     */
    public function getLayouts(): array
    {
        return $this->config['layouts'] ?? ['default'];
    }

    /**
     * Get theme templates.
     */
    public function getTemplates(): array
    {
        return $this->config['templates'] ?? [];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'slug' => $this->getSlug(),
            'version' => $this->getVersion(),
            'description' => $this->getDescription(),
            'author' => $this->getAuthor(),
            'parent' => $this->getParent(),
            'path' => $this->getPath(),
            'screenshot' => $this->getScreenshot(),
            'settings' => $this->getSettings(),
            'supports' => $this->config['supports'] ?? [],
            'locations' => $this->getLocations(),
            'layouts' => $this->getLayouts(),
            'templates' => $this->getTemplates(),
        ];
    }
}
