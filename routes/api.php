<?php

use App\Http\Controllers\Api\Auth\ForgotController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PermissionsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\ValidateJsonApiDocument;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth
Route::withoutMiddleware([ValidateJsonApiDocument::class])->group(function () {
    Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');
    Route::post('/auth/logout', [LogoutController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/register', [RegisterController::class, 'register'])->name('auth.register');
    Route::post('/auth/forgot-password', [ForgotController::class, 'forgot'])->name('auth.forgot');
    Route::post('/auth/reset-password', [ForgotController::class, 'reset'])->name('auth.reset');
    Route::post('/auth/email/resend', [VerifyEmailController::class, 'resend'])->name('verification.send');
    Route::get('/auth/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verifyEmail'])->name('verification.verify');
    Route::get('/oauth/{driver}', [SocialAuthController::class, 'redirectToProvider'])->name('social.oauth');
    Route::get('/oauth/{driver}/callback', [SocialAuthController::class,'handleProviderCallback'])->name('social.callback');
    Route::middleware(['auth:api'])->post('/auth/refresh-token', [RefreshTokenController::class])->name('auth.refresh-token');
});

// Public routes
Route::withoutMiddleware([ValidateJsonApiDocument::class])->prefix('public')->group(function () {
    // Packages
    Route::get('/packages', [PackageController::class, 'publicIndex'])->name('packages.publicIndex');
});

// Protected routes
Route::middleware(['auth:api'])->name('api.v1.')->group(function () {
    // Users
    Route::apiResource('/users', UserController::class);

    // Settings
    Route::apiResource('/settings', SettingController::class)->only(['index', 'show', 'update']);

    // Roles
    Route::apiResource('/roles', RolesController::class);

    // Permissions
    Route::get('/permissions', [PermissionsController::class, 'index'])->name('permissions.index');

    // Account
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('profile.profile');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('profile.update-profile');
    Route::patch('/account/change-password', [AccountController::class, 'changePassword'])->name('profile.change-password');
    Route::get('/account/devices-auth-list', [AccountController::class, 'devicesAuthList'])->name('profile.devices-auth-list');
    Route::withoutMiddleware(ValidateJsonApiDocument::class)->post('/account/logout-device', [AccountController::class, 'logoutDevice'])->name('profile.logout-device');
    Route::delete('/account/delete-account/{id}', [AccountController::class, 'deleteAccount'])->name('profile.delete-account');

    // Packages
    Route::apiResource('/packages', PackageController::class);

    // Subscriptions
    Route::apiResource('/subscriptions', SubscriptionController::class);
});
