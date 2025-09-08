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
use App\Http\Controllers\Api\Business\BrandController;
use App\Http\Controllers\Api\Business\BusinessController;
use App\Http\Controllers\Api\Business\BusinessLocationController;
use App\Http\Controllers\Api\Business\CategoryController;
use App\Http\Controllers\Api\Business\CurrencyController;
use App\Http\Controllers\Api\Business\InvoiceLayoutController;
use App\Http\Controllers\Api\Business\InvoiceSchemeController;
use App\Http\Controllers\Api\Business\ProductController;
use App\Http\Controllers\Api\Business\ReferenceCountController;
use App\Http\Controllers\Api\Business\TaxRateController;
use App\Http\Controllers\Api\Business\UnitController;
use App\Http\Controllers\Api\Business\PrinterController;
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

	// Reference Counts
	Route::apiResource('/reference-counts', ReferenceCountController::class)->only(['index']);

	// Currencies
	Route::apiResource('/currencies', CurrencyController::class);
	Route::delete('/currencies/bulk-destroy', [CurrencyController::class, 'bulkDestroy'])->name('currencies.bulk-destroy');
	Route::get('/currencies/{currency}/statistics', [CurrencyController::class, 'statistics'])->name('currencies.statistics');
	Route::get('/currencies/{currency}/businesses', [CurrencyController::class, 'businesses'])->name('currencies.businesses');

	// Businesses
	Route::apiResource('/businesses', BusinessController::class);
	Route::patch('/businesses/{business}/toggle-status', [BusinessController::class, 'toggleStatus'])->name('businesses.toggle-status');
	Route::get('/businesses/{business}/statistics', [BusinessController::class, 'statistics'])->name('businesses.statistics');

	// Business settings
	Route::prefix('business/{business}')->group(function () {

		// Invoice Schemes
		Route::delete('/invoice-schemes/bulk', [InvoiceSchemeController::class, 'bulkDestroy'])->name('businesses.invoice-schemes.bulk-destroy');
		Route::apiResource('/invoice-schemes', InvoiceSchemeController::class);
		Route::get('/invoice-schemes/{invoice_scheme}/statistics', [InvoiceSchemeController::class, 'statistics'])->name('businesses.invoice-schemes.statistics');
		Route::patch('/invoice-schemes/{invoice_scheme}/status', [InvoiceSchemeController::class, 'changeStatus'])->name('businesses.invoice-schemes.status');

		// Invoice Layouts
		Route::delete('/invoice-layouts/bulk', [InvoiceLayoutController::class, 'bulkDestroy'])->name('businesses.invoice-layouts.bulk-destroy');
		Route::apiResource('/invoice-layouts', InvoiceLayoutController::class);
		Route::get('/invoice-layouts/{invoice_layout}/statistics', [InvoiceLayoutController::class, 'statistics'])->name('businesses.invoice-layouts.statistics');
		Route::patch('/invoice-layouts/{invoice_layout}/status', [InvoiceLayoutController::class, 'changeStatus'])->name('businesses.invoice-layouts.status');
		Route::post('/invoice-layouts/{invoice_layout}/duplicate', [InvoiceLayoutController::class, 'duplicate'])->name('businesses.invoice-layouts.duplicate');

		// Printers
		Route::apiResource('/printers', PrinterController::class);

		// Business Locations
		Route::delete('/business-locations/bulk-destroy', [BusinessLocationController::class, 'bulkDestroy'])->name('businesses.locations.bulk-destroy');
		Route::apiResource('/business-locations', BusinessLocationController::class);
		Route::get('/business-locations/{location}/statistics', [BusinessLocationController::class, 'statistics'])->name('businesses.locations.statistics');
		Route::patch('/business-locations/{location}/toggle-status', [BusinessLocationController::class, 'changeStatus'])->name('businesses.locations.toggle-status');
		Route::post('/business-locations/{location}/restore', [BusinessLocationController::class, 'restore'])->withTrashed()->name('businesses.locations.restore');

		// Brands
		Route::delete('/brands/bulk', [BrandController::class, 'bulkDestroy'])->name('businesses.brands.bulk-destroy');
		Route::apiResource('/brands', BrandController::class);
		Route::get('/brands/{brand}/statistics', [BrandController::class, 'statistics'])->name('businesses.brands.statistics');
		Route::get('/brands/{brand}/products', [BrandController::class, 'products'])->name('businesses.brands.products');
		Route::patch('/brands/{brand}/status', [BrandController::class, 'changeStatus'])->name('businesses.brands.status');
		Route::post('/brands/{brand}/restore', [BrandController::class, 'restore'])->name('businesses.brands.restore');

		// Categories
		Route::delete('/categories/bulk', [CategoryController::class, 'bulkDestroy'])->name('businesses.categories.bulk-destroy');
		Route::apiResource('/categories', CategoryController::class);
		Route::get('/categories/{category}/statistics', [CategoryController::class, 'statistics'])->name('businesses.categories.statistics');
		Route::get('/categories/{category}/products', [CategoryController::class, 'products'])->name('businesses.categories.products');
		Route::patch('/categories/{category}/status', [CategoryController::class, 'changeStatus'])->name('businesses.categories.status');
		Route::post('/categories/{category}/restore', [CategoryController::class, 'restore'])->name('businesses.categories.restore');

		// Tax Rates
		Route::delete('/tax-rates/bulk', [TaxRateController::class, 'bulkDestroy'])->name('businesses.tax-rates.bulk-destroy');
		Route::apiResource('/tax-rates', TaxRateController::class);
		Route::get('/tax-rates/{tax_rate}/statistics', [TaxRateController::class, 'statistics'])->name('businesses.tax-rates.statistics');
		Route::patch('/tax-rates/{tax_rate}/status', [TaxRateController::class, 'changeStatus'])->name('businesses.tax-rates.status');
		Route::post('/tax-rates/{tax_rate}/restore', [TaxRateController::class, 'restore'])->withTrashed()->name('businesses.tax-rates.restore');

		// Units
		Route::delete('/units/bulk', [UnitController::class, 'bulkDestroy'])->name('businesses.units.bulk-destroy');
		Route::apiResource('/units', UnitController::class);
		Route::get('/units/{unit}/statistics', [UnitController::class, 'statistics'])->name('businesses.units.statistics');
		Route::get('/units/{unit}/products', [UnitController::class, 'products'])->name('businesses.units.products');
		Route::patch('/units/{unit}/status', [UnitController::class, 'changeStatus'])->name('businesses.units.status');
		Route::post('/units/{unit}/restore', [UnitController::class, 'restore'])->name('businesses.units.restore');

		// Products
		Route::apiResource('/products', ProductController::class);
		Route::patch('/products/{product}/toggle-stock', [ProductController::class, 'toggleStock'])->name('products.toggle-stock');
		Route::get('/products/{product}/stock', [ProductController::class, 'stock'])->name('products.stock');
		Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
	});
});
