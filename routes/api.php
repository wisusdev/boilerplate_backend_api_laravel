<?php

use App\Http\Controllers\Api\Auth\ForgotController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
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
Route::group(['prefix' => '/auth'], function () {
	Route::post('/login', [LoginController::class, 'login']);
	Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:api');
	Route::post('/register', [RegisterController::class, 'register']);
	Route::post('/forgot-password', [ForgotController::class, 'forgot']);
	Route::post('/reset-password', [ForgotController::class, 'reset']);
});

Route::group(['middleware' => 'auth:api'], function () {
	// User
	Route::get('/user', [LoginController::class, 'user']);
});
