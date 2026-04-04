<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeController extends Controller
{
    /** The real theme path is base_path('themes') — managed by the ThemeManager CMS system */
    private string $themesPath;

    public function __construct()
    {
        $this->themesPath = config('theme.path', base_path('themes'));
    }

    public function index(): View
    {
        $themes      = $this->scanThemes();
        $activeTheme = $this->getActiveTheme();

        return view('admin.themes.index', compact('themes', 'activeTheme'));
    }

    public function activate(string $theme): RedirectResponse
    {
        $themes = $this->scanThemes();

        if (!isset($themes[$theme])) {
            return back()->with('error', __('Theme ":theme" not found.', ['theme' => $theme]));
        }

        Setting::updateOrCreate(
            ['key' => 'active_theme'],
            ['value' => $theme]
        );

        $label = $themes[$theme]['label'] ?? ucfirst($theme);

        return redirect()->route('admin.themes.index')
            ->with('success', __('Theme ":theme" activated successfully.', ['theme' => $label]));
    }

    public function install(Request $request): RedirectResponse
    {
        $request->validate([
            'theme_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $zip = new \ZipArchive();

        $tmpPath = $request->file('theme_zip')->getRealPath();

        if ($zip->open($tmpPath) !== true) {
            return back()->with('error', __('Could not open the ZIP file.'));
        }

        // Look for theme.json inside the ZIP (determines theme slug)
        $themeJson = null;
        $themeDir  = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+)/theme\.json$#', $name, $m)) {
                $themeJson = json_decode($zip->getFromIndex($i), true);
                $themeDir  = $m[1];
                break;
            }
        }

        if (!$themeJson || empty($themeJson['slug'])) {
            $zip->close();
            return back()->with('error', __('Invalid theme ZIP: missing theme.json with a "slug" field.'));
        }

        $slug        = preg_replace('/[^a-z0-9\-_]/', '', strtolower($themeJson['slug']));
        $destination = $this->themesPath . '/' . $slug;

        if (is_dir($destination)) {
            // Remove existing version before overwriting
            $this->deleteDirectory($destination);
        }

        mkdir($destination, 0755, true);

        // Extract only files that belong to the theme directory
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (!str_starts_with($name, $themeDir . '/')) {
                continue;
            }

            $relative = substr($name, strlen($themeDir . '/'));

            if ($relative === '' || str_ends_with($relative, '/')) {
                // Directory entry
                $dir = $destination . '/' . $relative;
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                continue;
            }

            // Prevent path traversal
            $realDest = realpath($destination) . '/' . $relative;
            if (!str_starts_with($realDest, realpath($destination))) {
                continue;
            }

            $parentDir = dirname($realDest);
            if (!is_dir($parentDir)) {
                mkdir($parentDir, 0755, true);
            }

            file_put_contents($realDest, $zip->getFromIndex($i));
        }

        $zip->close();

        $label = $themeJson['name'] ?? ucfirst($slug);

        return redirect()->route('admin.themes.index')
            ->with('success', __('Theme ":theme" installed successfully.', ['theme' => $label]));
    }

    public function uninstall(string $theme): RedirectResponse
    {
        $themePath = $this->themesPath . '/' . $theme;

        if (!is_dir($themePath)) {
            return back()->with('error', __('Theme ":theme" not found.', ['theme' => $theme]));
        }

        if ($this->getActiveTheme() === $theme) {
            return back()->with('error', __('Cannot uninstall the active theme. Activate another theme first.'));
        }

        $this->deleteDirectory($themePath);

        return redirect()->route('admin.themes.index')
            ->with('success', __('Theme ":theme" uninstalled.', ['theme' => $theme]));
    }

    private function getActiveTheme(): string
    {
        return Setting::where('key', 'active_theme')->value('value') ?? 'default';
    }

    /** Returns themes keyed by slug, scanning base_path('themes') */
    private function scanThemes(): array
    {
        if (!is_dir($this->themesPath)) {
            mkdir($this->themesPath, 0755, true);
        }

        $themes = [];

        foreach (glob($this->themesPath . '/*', GLOB_ONLYDIR) as $dir) {
            $manifest = $dir . '/theme.json';
            $name     = basename($dir);

            $data = is_file($manifest)
                ? json_decode(file_get_contents($manifest), true) ?? []
                : [];

            $themes[$name] = [
                'label'       => $data['name'] ?? ucfirst($name),
                'description' => $data['description'] ?? '',
                'version'     => $data['version'] ?? '1.0.0',
                'author'      => $data['author'] ?? '—',
                'preview'     => $data['preview'] ?? null,
                'path'        => 'themes/' . $name,
                'custom'      => true,
            ];
        }

        return $themes;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $path . '/' . $item;
            is_dir($full) ? $this->deleteDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

