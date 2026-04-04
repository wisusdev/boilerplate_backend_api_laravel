<?php

namespace App\CMS\Theme\Middleware;

use App\CMS\Theme\Contracts\ThemeManagerInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThemeMiddleware
{
    protected ThemeManagerInterface $themeManager;

    public function __construct(ThemeManagerInterface $themeManager)
    {
        $this->themeManager = $themeManager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $theme = null, ?string $parentTheme = null): Response
    {
        if ($theme) {
            // If a parent theme is specified, we need to temporarily modify the theme config
            if ($parentTheme && $this->themeManager->exists($parentTheme)) {
                // This creates a child-parent relationship for this request
                $this->setThemeWithParent($theme, $parentTheme);
            } elseif ($this->themeManager->exists($theme)) {
                $this->themeManager->set($theme);
            }
        }

        return $next($request);
    }

    /**
     * Set a theme with a specific parent override.
     */
    protected function setThemeWithParent(string $theme, string $parentTheme): void
    {
        // First set the theme
        if ($this->themeManager->exists($theme)) {
            $this->themeManager->set($theme);
        }

        // Then manually set parent if different from configured
        // This would require extending ThemeManager, for now we just set the theme
    }
}
