<?php

namespace App\CMS\Theme\Contracts;

interface ThemeInterface
{
    /**
     * Get the theme name.
     */
    public function getName(): string;

    /**
     * Get the theme slug.
     */
    public function getSlug(): string;

    /**
     * Get the theme version.
     */
    public function getVersion(): string;

    /**
     * Get the theme description.
     */
    public function getDescription(): string;

    /**
     * Get the theme author.
     */
    public function getAuthor(): string;

    /**
     * Get the parent theme name if exists.
     */
    public function getParent(): ?string;

    /**
     * Get the theme path.
     */
    public function getPath(): string;

    /**
     * Get the views path.
     */
    public function getViewsPath(): string;

    /**
     * Get the assets path.
     */
    public function getAssetsPath(): string;

    /**
     * Get theme configuration.
     */
    public function getConfig(string $key = null, mixed $default = null): mixed;

    /**
     * Get theme screenshot URL.
     */
    public function getScreenshot(): ?string;

    /**
     * Check if theme supports a feature.
     */
    public function supports(string $feature): bool;

    /**
     * Get theme settings.
     */
    public function getSettings(): array;
}
