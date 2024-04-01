<?php

use App\Http\Controllers\Api\Auth\ForgotController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
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
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/register', [RegisterController::class, 'register'])->name('auth.register');
    Route::post('/auth/forgot-password', [ForgotController::class, 'forgot'])->name('auth.forgot');
    Route::post('/auth/reset-password', [ForgotController::class, 'reset'])->name('auth.reset');
});

Route::group(['middleware' => 'auth:api'], function () {
    Route::apiResource('/users', UserController::class);
});
