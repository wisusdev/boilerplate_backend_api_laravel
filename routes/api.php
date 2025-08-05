<?php

use App\Http\Controllers\Api\Auth\ForgotController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Base\AccountController;
use App\Http\Controllers\Api\Base\PermissionsController;
use App\Http\Controllers\Api\Base\RolesController;
use App\Http\Controllers\Api\Base\UserController;
use App\Http\Middleware\ValidateJsonApiDocument;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider, and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth
Route::prefix('auth')->withoutMiddleware([ValidateJsonApiDocument::class])->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->name('auth.login');
    Route::post('/register', [RegisterController::class, 'register'])->name('auth.register');
    Route::post('/logout', [LogoutController::class, 'logout'])->name('auth.logout')->middleware('auth:api');
    Route::post('/forgot-password', [ForgotController::class, 'forgot'])->name('auth.forgot');
    Route::post('/reset-password', [ForgotController::class, 'reset'])->name('auth.reset');
    Route::post('/email/resend', [VerifyEmailController::class, 'resend'])->name('verification.send')->middleware('auth:api');
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('/refresh-token', [RefreshTokenController::class, 'refreshToken'])->name('auth.refresh-token')->middleware('auth:api');
    Route::post('/verify-social-token', [SocialAuthController::class, 'verifySocialToken'])->name('auth.verify-social-token');
});

// Protected routes
Route::middleware(['auth:api'])->group(function () {
    // Permissions
    Route::get('/permissions', [PermissionsController::class, 'index'])->name('permissions.index');

    // Roles
    Route::apiResource('/roles', RolesController::class);

    // Users
    Route::apiResource('/users', UserController::class);

    // Account
    Route::prefix('account')->group(function () {
        Route::get('/profile', [AccountController::class, 'profile'])->name('account.profile');
        Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('account.update-profile');
        Route::patch('/change-password', [AccountController::class, 'changePassword'])->name('account.change-password');
        Route::post('/delete-account', [AccountController::class, 'deleteAccount'])->name('account.delete-account');
        Route::delete('/delete-account-verify', [AccountController::class, 'deleteAccountVerify'])->name('account.delete-account-verify');
    });
});
