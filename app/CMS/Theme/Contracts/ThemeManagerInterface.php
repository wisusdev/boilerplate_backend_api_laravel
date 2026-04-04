<?php

namespace App\CMS\Theme\Contracts;

interface ThemeManagerInterface
{
    /**
     * Set the active theme.
     */
    public function set(string $theme): void;

    /**
     * Get the active theme.
     */
    public function active(): ?ThemeInterface;

    /**
     * Get the active theme name.
     */
    public function getActive(): ?string;

    /**
     * Get the parent theme if exists.
     */
    public function parent(): ?ThemeInterface;

    /**
     * Get all available themes.
     */
    public function all(): array;

    /**
     * Get a theme by name.
     */
    public function find(string $theme): ?ThemeInterface;

    /**
     * Check if a theme exists.
     */
    public function exists(string $theme): bool;

    /**
     * Clear the active theme.
     */
    public function clear(): void;

    /**
     * Get the theme path.
     */
    public function path(string $path = '', ?string $theme = null): string;

    /**
     * Get view paths for the finder.
     */
    public function getViewPaths(): array;

    /**
     * Get the asset URL for a theme asset.
     */
    public function asset(string $path, ?string $theme = null): string;
}
