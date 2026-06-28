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
use App\Http\Controllers\Api\Base\SettingsController;
use App\Http\Controllers\Api\Travel\CurrencyController;
use App\Http\Controllers\Api\Travel\GalleryController;
use App\Http\Controllers\Api\Travel\CustomInquiryController;
use App\Http\Controllers\Api\Travel\BookingController;
use App\Http\Controllers\Api\Travel\InvoiceController;
use App\Http\Controllers\Api\Travel\PaymentController;
use App\Http\Controllers\Api\Travel\ReviewController;
use App\Http\Controllers\Api\Travel\ReportController;
use App\Http\Controllers\Api\Travel\TransportVehicleController;
use App\Http\Controllers\Api\Travel\WebhookController;
use App\Http\Controllers\Api\Travel\TourController;
use App\Http\Controllers\Api\Travel\TourCategoryController;
use App\Http\Middleware\ValidateJsonApiHeaders;
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
Route::prefix('auth')->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class,])->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->name('auth.login')->middleware('throttle:auth');
    Route::post('/register', [RegisterController::class, 'register'])->name('auth.register')->middleware('throttle:auth-register');
    Route::post('/logout', [LogoutController::class, 'logout'])->name('auth.logout')->middleware('auth:api');
    Route::post('/forgot-password', [ForgotController::class, 'forgot'])->name('auth.forgot')->middleware('throttle:auth-forgot');
    Route::post('/reset-password', [ForgotController::class, 'reset'])->name('auth.reset')->middleware('throttle:auth');
    Route::post('/email/resend', [VerifyEmailController::class, 'resend'])->name('verification.send')->middleware(['auth:api', 'throttle:email-resend']);
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('/refresh-token', [RefreshTokenController::class, 'refreshToken'])->name('auth.refresh-token')->middleware('auth:api');
    Route::post('/verify-social-token', [SocialAuthController::class, 'verifySocialToken'])->name('auth.verify-social-token')->middleware('throttle:auth');
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

    // Tours (admin write)
    Route::prefix('tours')->name('api.v1.tours.')->middleware('role:admin|super-admin')->group(function () {
        Route::post('/', [TourController::class, 'store'])->name('store');
        Route::patch('/{tour}', [TourController::class, 'update'])->name('update');
        // Media – estos endpoints usan multipart/form-data, sin middleware JSON:API
        Route::post('/{tour}/featured-image', [TourController::class, 'uploadFeaturedImage'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('featured-image.upload');
        Route::post('/{tour}/gallery', [TourController::class, 'uploadGalleryImages'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('gallery.upload');
        Route::delete('/{tour}/gallery/{media}', [TourController::class, 'destroyGalleryImage'])
            ->name('gallery.destroy');
    });

    // Transport Vehicles (admin write)
    Route::prefix('transport-vehicles')->name('api.v1.transport_vehicles.')->middleware('role:admin|super-admin')->group(function () {
        Route::post('/', [TransportVehicleController::class, 'store'])->name('store');
        Route::patch('/{transportVehicle}', [TransportVehicleController::class, 'update'])->name('update');
        // Media
        Route::post('/{transportVehicle}/featured-image', [TransportVehicleController::class, 'uploadFeaturedImage'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('featured-image.upload');
        Route::post('/{transportVehicle}/gallery', [TransportVehicleController::class, 'uploadGalleryImages'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('gallery.upload');
        Route::delete('/{transportVehicle}/gallery/{media}', [TransportVehicleController::class, 'destroyGalleryImage'])
            ->name('gallery.destroy');
    });

    // Admin write: currencies, tour categories, reviews
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::post('/currencies', [CurrencyController::class, 'store'])->name('api.v1.currencies.store');

        // Tour Categories (admin write)
        Route::post('/tour-categories', [TourCategoryController::class, 'store'])->name('api.v1.tour_categories.store');
        Route::patch('/tour-categories/{tourCategory}', [TourCategoryController::class, 'update'])->name('api.v1.tour_categories.update');
        Route::delete('/tour-categories/{tourCategory}', [TourCategoryController::class, 'destroy'])->name('api.v1.tour_categories.destroy');

        // Reviews (admin write)
        Route::post('/reviews', [ReviewController::class, 'store'])->name('api.v1.reviews.store');
        Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->name('api.v1.reviews.update');
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('api.v1.reviews.destroy');
    });

    // Bookings
    Route::prefix('bookings')->name('api.v1.bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
        Route::patch('/{booking}', [BookingController::class, 'update'])->name('update');
    });

    Route::prefix('custom-inquiries')->name('api.v1.custom_inquiries.')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [CustomInquiryController::class, 'index'])->name('index');
        Route::get('/{customInquiry}', [CustomInquiryController::class, 'show'])->name('show');
    });

    Route::prefix('payments')->name('api.v1.payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::post('/checkout', [PaymentController::class, 'checkout'])->name('checkout');
        Route::post('/verify', [PaymentController::class, 'verify'])->name('verify');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
    });

    // Invoices + DTE, Settings, Reports, Gallery (admin only)
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::prefix('invoices')->name('api.v1.invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
            Route::patch('/{invoice}', [InvoiceController::class, 'update'])->name('update');
            Route::post('/{invoice}/generate-dte', [InvoiceController::class, 'generateDte'])->name('generate-dte');
            Route::get('/{invoice}/preview-dte', [InvoiceController::class, 'previewDte'])->name('preview-dte');
        });
        Route::post('/settings/dte-certificate', [InvoiceController::class, 'uploadCertificate'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('api.v1.settings.dte-certificate');

        // Settings (admin write)
        Route::patch('/settings', [SettingsController::class, 'update'])->name('api.v1.settings.update');
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('api.v1.settings.logo');

        Route::get('/reports/overview', [ReportController::class, 'overview'])->name('api.v1.reports.overview');

        // Gallery (admin-only write, public read is below)
        Route::post('/gallery', [GalleryController::class, 'store'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('api.v1.gallery.store');
        Route::delete('/gallery/{galleryItem}', [GalleryController::class, 'destroy'])->name('api.v1.gallery.destroy');
        Route::patch('/gallery/reorder', [GalleryController::class, 'reorder'])->name('api.v1.gallery.reorder');
    });
});

// Payment webhooks (server-to-server). Públicos y sin JSON:API: cada gateway
// envía su propio Content-Type y se autentica mediante firma, no Bearer token.
Route::post('/payments/webhook/{gateway}', [WebhookController::class, 'handle'])
    ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
    ->middleware('throttle:120,1')
    ->where('gateway', 'stripe|paypal|wompi')
    ->name('api.v1.payments.webhook');

// Public tours
Route::prefix('tours')->name('api.v1.tours.')->group(function () {
    Route::get('/', [TourController::class, 'index'])->name('index');
    Route::get('/{tour}', [TourController::class, 'show'])->name('show');
});

Route::prefix('transport-vehicles')->name('api.v1.transport_vehicles.')->group(function () {
    Route::get('/', [TransportVehicleController::class, 'index'])->name('index');
    Route::get('/{transportVehicle}', [TransportVehicleController::class, 'show'])->name('show');
    Route::get('/{transportVehicle}/availability', [TransportVehicleController::class, 'checkAvailability'])->name('availability');
});

Route::get('/currencies', [CurrencyController::class, 'index'])->name('api.v1.currencies.index');
Route::get('/currencies/{currency}', [CurrencyController::class, 'show'])->name('api.v1.currencies.show');

// Tour Categories (public read)
Route::get('/tour-categories', [TourCategoryController::class, 'index'])->name('api.v1.tour_categories.index');

// Reviews (public read)
Route::get('/reviews', [ReviewController::class, 'index'])->name('api.v1.reviews.index');

Route::post('/custom-inquiries', [CustomInquiryController::class, 'store'])->name('api.v1.custom_inquiries.store')->middleware('throttle:forms');

// Gallery (public read)
Route::get('/gallery', [GalleryController::class, 'index'])->name('api.v1.gallery.index');

// Settings (public read — returns safe subset without credentials)
Route::get('/settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
