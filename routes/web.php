<?php

use App\Http\Controllers\Web\Admin\ModuleController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\SettingController;
use App\Http\Controllers\Web\Admin\ThemeController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('home');
})->name('home');

// Auth routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Admin panel (auth required)
Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Roles
    Route::resource('roles', RoleController::class)->except(['show']);

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    // Modules
    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::post('modules/install', [ModuleController::class, 'install'])->name('modules.install');
    Route::post('modules/{module}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');
    Route::delete('modules/{module}', [ModuleController::class, 'uninstall'])->name('modules.uninstall');

    // Themes
    Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::post('themes/install', [ThemeController::class, 'install'])->name('themes.install');
    Route::post('themes/{theme}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
    Route::delete('themes/{theme}', [ThemeController::class, 'uninstall'])->name('themes.uninstall');
});

// Serve public storage files — needed for `php artisan serve` which doesn't follow symlinks
Route::get('/storage/{path}', function (string $path) {
    $file = storage_path('app/public/' . $path);

    abort_unless(file_exists($file) && is_file($file), 404);

    return response()->file($file);
})->where('path', '.*');

// Pages catch-all route (MUST be last to avoid intercepting other routes)
Route::middleware(['web'])->get('{slug}', [\Modules\Pages\Http\Controllers\PageController::class, 'show'])
    ->where('slug', '.*')
    ->name('page.show');