<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Nwidart\Modules\Facades\Module;

class ModuleController extends Controller
{
    /** Modules that cannot be disabled */
    private const PROTECTED = ['Core'];

    /** Root path where nwidart/laravel-modules looks for modules */
    private string $modulesPath;

    public function __construct()
    {
        // Reads the configured modules path from nwidart/laravel-modules
        $this->modulesPath = config('modules.paths.modules', base_path('Modules'));
    }

    public function index(): View
    {
        $modules = collect(Module::all())->map(function ($module) {
            return [
                'name'        => $module->getName(),
                'alias'       => $module->getLowerName(),
                'description' => $module->getDescription() ?: '—',
                'version'     => $module->get('version', '1.0.0'),
                'enabled'     => $module->isEnabled(),
                'protected'   => in_array($module->getName(), self::PROTECTED),
                'path'        => str_replace(base_path() . '/', '', $module->getPath()),
            ];
        })->values();

        return view('admin.modules.index', compact('modules'));
    }

    public function install(Request $request): RedirectResponse
    {
        $request->validate([
            'module_zip' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ]);

        $zip     = new \ZipArchive();
        $tmpPath = $request->file('module_zip')->getRealPath();

        if ($zip->open($tmpPath) !== true) {
            return back()->with('error', __('Could not open the ZIP file.'));
        }

        // Find module.json inside the ZIP (determines module name)
        $moduleJson = null;
        $moduleDir  = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+)/module\.json$#', $name, $m)) {
                $moduleJson = json_decode($zip->getFromIndex($i), true);
                $moduleDir  = $m[1];
                break;
            }
        }

        if (!$moduleJson || empty($moduleJson['name'])) {
            $zip->close();
            return back()->with('error', __('Invalid module ZIP: missing module.json with a "name" field.'));
        }

        $moduleName  = preg_replace('/[^A-Za-z0-9]/', '', $moduleJson['name']);
        $destination = $this->modulesPath . '/' . $moduleName;

        if (is_dir($destination)) {
            $this->deleteDirectory($destination);
        }

        mkdir($destination, 0755, true);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (!str_starts_with($name, $moduleDir . '/')) {
                continue;
            }

            $relative = substr($name, strlen($moduleDir . '/'));

            if ($relative === '' || str_ends_with($relative, '/')) {
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

        // Run module migration (best-effort — may not have migrations)
        \Artisan::call('module:migrate', ['module' => $moduleName, '--force' => true]);

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":module" installed successfully.', ['module' => $moduleName]));
    }

    public function uninstall(string $name): RedirectResponse
    {
        if (in_array($name, self::PROTECTED)) {
            return back()->with('error', __('This module is protected and cannot be uninstalled.'));
        }

        $module = Module::find($name);

        if (!$module) {
            return back()->with('error', __('Module ":module" not found.', ['module' => $name]));
        }

        $path = $module->getPath();
        $module->delete();

        if (is_dir($path)) {
            $this->deleteDirectory($path);
        }

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module ":module" uninstalled.', ['module' => $name]));
    }

    public function toggle(string $name): RedirectResponse|JsonResponse
    {
        $module = Module::find($name);

        if (!$module) {
            return $this->respond($name, false, __('Module not found.'), 404);
        }

        if (in_array($name, self::PROTECTED)) {
            return $this->respond($name, $module->isEnabled(), __('This module is protected and cannot be disabled.'), 422);
        }

        if ($module->isEnabled()) {
            $module->disable();
            $message = __(':module disabled successfully.', ['module' => $name]);
        } else {
            $module->enable();
            $message = __(':module enabled successfully.', ['module' => $name]);
        }

        return $this->respond($name, $module->isEnabled(), $message);
    }

    private function respond(string $name, bool $enabled, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if (request()->expectsJson()) {
            return response()->json(['module' => $name, 'enabled' => $enabled, 'message' => $message], $status);
        }

        if ($status !== 200) {
            return back()->with('error', $message);
        }

        return redirect()->route('admin.modules.index')->with('success', $message);
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
