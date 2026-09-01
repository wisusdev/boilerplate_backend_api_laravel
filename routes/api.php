<?php

use App\Http\Controllers\Api\Auth\ForgotController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Base\AccountController;
use App\Http\Controllers\Api\Base\InstallController;
use App\Http\Controllers\Api\Base\PermissionsController;
use App\Http\Controllers\Api\Base\RolesController;
use App\Http\Controllers\Api\Base\SettingsController;
use App\Http\Controllers\Api\Base\UserController;
use App\Http\Controllers\Api\Travel\BookingController;
use App\Http\Controllers\Api\Travel\CouponController;
use App\Http\Controllers\Api\Travel\CurrencyController;
use App\Http\Controllers\Api\Travel\CustomInquiryController;
use App\Http\Controllers\Api\Travel\ExpenseCategoryController;
use App\Http\Controllers\Api\Travel\ExpenseController;
use App\Http\Controllers\Api\Travel\FinanceController;
use App\Http\Controllers\Api\Travel\GalleryController;
use App\Http\Controllers\Api\Travel\GuideController;
use App\Http\Controllers\Api\Travel\InvoiceController;
use App\Http\Controllers\Api\Travel\PaymentController;
use App\Http\Controllers\Api\Travel\PaymentLinkController;
use App\Http\Controllers\Api\Travel\ProductReviewController;
use App\Http\Controllers\Api\Travel\ReportController;
use App\Http\Controllers\Api\Travel\ReviewController;
use App\Http\Controllers\Api\Travel\SubscriberController;
use App\Http\Controllers\Api\Travel\TourCategoryController;
use App\Http\Controllers\Api\Travel\TourController;
use App\Http\Controllers\Api\Travel\TransportVehicleController;
use App\Http\Controllers\Api\Travel\WebhookController;
use App\Http\Middleware\ValidateJsonApiDocument;
use App\Http\Middleware\ValidateJsonApiHeaders;
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

// Instalador (estilo WordPress). Público mientras no exista un administrador;
// el POST se autobloquea (409) una vez instalado.
Route::prefix('install')->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->group(function () {
    Route::get('/status', [InstallController::class, 'status'])->name('install.status');
    Route::get('/requirements', [InstallController::class, 'requirements'])->name('install.requirements');
    Route::post('/', [InstallController::class, 'install'])->name('install.run')->middleware('throttle:auth');
});

// Auth
Route::prefix('auth')->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->name('auth.login')->middleware('throttle:auth');
    Route::post('/register', [RegisterController::class, 'register'])->name('auth.register')->middleware('throttle:auth-register');
    Route::post('/logout', [LogoutController::class, 'logout'])->name('auth.logout')->middleware('auth:api');
    Route::post('/forgot-password', [ForgotController::class, 'forgot'])->name('auth.forgot')->middleware('throttle:auth-forgot');
    Route::post('/reset-password', [ForgotController::class, 'reset'])->name('auth.reset')->middleware('throttle:auth');
    Route::post('/email/resend', [VerifyEmailController::class, 'resend'])->name('verification.send')->middleware(['auth:api', 'throttle:email-resend']);
    // Enlace firmado y temporal (ver App\Notifications\VerifyEmail). 'signed:relative'
    // porque la firma se genera sobre la URI relativa (absolute: false).
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verifyEmail'])
        ->middleware(['signed:relative', 'throttle:auth'])
        ->name('verification.verify');
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
        // Acción sin documento: pide el enlace de confirmación y no lleva cuerpo,
        // así que no puede satisfacer el `data.attributes` que exige JSON:API.
        Route::post('/delete-account', [AccountController::class, 'deleteAccount'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('account.delete-account');
        Route::delete('/delete-account-verify', [AccountController::class, 'deleteAccountVerify'])->name('account.delete-account-verify');
    });

    // Tours (escritura admin — gateada por permiso)
    Route::prefix('tours')->name('api.v1.tours.')->group(function () {
        Route::post('/', [TourController::class, 'store'])->middleware('permission:tours:store')->name('store');
        Route::patch('/{tour}', [TourController::class, 'update'])->middleware('permission:tours:update')->name('update');
        // Media – estos endpoints usan multipart/form-data, sin middleware JSON:API
        Route::post('/{tour}/featured-image', [TourController::class, 'uploadFeaturedImage'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:tours:media')
            ->name('featured-image.upload');
        Route::post('/{tour}/gallery', [TourController::class, 'uploadGalleryImages'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:tours:media')
            ->name('gallery.upload');
        Route::delete('/{tour}/gallery/{media}', [TourController::class, 'destroyGalleryImage'])
            ->middleware('permission:tours:media')
            ->name('gallery.destroy');
        Route::delete('/{tour}', [TourController::class, 'destroy'])
            ->middleware('permission:tours:delete')->name('destroy');
    });

    // Transport Vehicles (escritura admin — gateada por permiso)
    Route::prefix('transport-vehicles')->name('api.v1.transport_vehicles.')->group(function () {
        Route::post('/', [TransportVehicleController::class, 'store'])->middleware('permission:transport-vehicles:store')->name('store');
        Route::patch('/{transportVehicle}', [TransportVehicleController::class, 'update'])->middleware('permission:transport-vehicles:update')->name('update');
        // Media
        Route::post('/{transportVehicle}/featured-image', [TransportVehicleController::class, 'uploadFeaturedImage'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:transport-vehicles:media')
            ->name('featured-image.upload');
        Route::post('/{transportVehicle}/gallery', [TransportVehicleController::class, 'uploadGalleryImages'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:transport-vehicles:media')
            ->name('gallery.upload');
        Route::delete('/{transportVehicle}/gallery/{media}', [TransportVehicleController::class, 'destroyGalleryImage'])
            ->middleware('permission:transport-vehicles:media')
            ->name('gallery.destroy');
        Route::delete('/{transportVehicle}', [TransportVehicleController::class, 'destroy'])
            ->middleware('permission:transport-vehicles:delete')->name('destroy');
    });

    // Escritura admin — cada ruta gateada por su permiso.
    Route::post('/currencies', [CurrencyController::class, 'store'])->middleware('permission:currencies:store')->name('api.v1.currencies.store');
    Route::patch('/currencies/{currency}', [CurrencyController::class, 'update'])->middleware('permission:currencies:update')->name('api.v1.currencies.update');

    // Tour Categories (admin write)
    Route::post('/tour-categories', [TourCategoryController::class, 'store'])->middleware('permission:tour-categories:store')->name('api.v1.tour_categories.store');
    Route::patch('/tour-categories/{tourCategory}', [TourCategoryController::class, 'update'])->middleware('permission:tour-categories:update')->name('api.v1.tour_categories.update');
    Route::delete('/tour-categories/{tourCategory}', [TourCategoryController::class, 'destroy'])->middleware('permission:tour-categories:delete')->name('api.v1.tour_categories.destroy');

    // Reviews (admin write)
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('permission:reviews:store')->name('api.v1.reviews.store');
    Route::patch('/reviews/{review}', [ReviewController::class, 'update'])->middleware('permission:reviews:update')->name('api.v1.reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->middleware('permission:reviews:delete')->name('api.v1.reviews.destroy');

    // Cupones (CRUD admin)
    Route::prefix('coupons')->name('api.v1.coupons.')->group(function () {
        Route::get('/', [CouponController::class, 'index'])->middleware('permission:coupons:index')->name('index');
        Route::post('/', [CouponController::class, 'store'])->middleware('permission:coupons:store')->name('store');
        Route::patch('/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons:update')->name('update');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons:delete')->name('destroy');
    });

    // ── Finanzas: rentabilidad por tour (gastos + ingresos derivados de reservas) ──
    // Dashboard de rentabilidad.
    Route::get('/finance/summary', [FinanceController::class, 'summary'])->middleware('permission:finance:view')->name('api.v1.finance.summary');

    // Categorías de gasto (CRUD).
    Route::prefix('expense-categories')->name('api.v1.expense-categories.')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index'])->middleware('permission:expense-categories:index')->name('index');
        Route::post('/', [ExpenseCategoryController::class, 'store'])->middleware('permission:expense-categories:store')->name('store');
        Route::patch('/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->middleware('permission:expense-categories:update')->name('update');
        Route::delete('/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->middleware('permission:expense-categories:delete')->name('destroy');
    });

    // Gastos (CRUD + recibo). El rol 'guia' tiene index/store; el ownership por
    // guide_id lo aplica ExpenseController (un guía solo ve/crea los suyos).
    Route::prefix('expenses')->name('api.v1.expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expenses:index')->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expenses:store')->name('store');
        Route::patch('/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses:update')->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses:delete')->name('destroy');
        // El recibo se adjunta al crear el gasto; un 'guia' (con expenses:store pero
        // sin expenses:update) puede subirlo SOLO a su propio gasto (guard en el controlador).
        Route::post('/{expense}/receipt', [ExpenseController::class, 'uploadReceipt'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:expenses:store')->name('receipt.upload');
    });

    // Guías (usuarios con rol 'guia'): listar, otorgar y revocar el rol.
    Route::prefix('guides')->name('api.v1.guides.')
        ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->group(function () {
            Route::get('/', [GuideController::class, 'index'])->middleware('permission:guides:index')->name('index');
            Route::post('/', [GuideController::class, 'store'])->middleware('permission:guides:store')->name('store');
            Route::delete('/{user}', [GuideController::class, 'destroy'])->middleware('permission:guides:delete')->name('destroy');
        });

    // Validación de cupón (cualquier usuario autenticado; previsualiza el descuento).
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon'])
        ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
        ->name('api.v1.coupons.validate');

    // Bookings
    Route::prefix('bookings')->name('api.v1.bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
        Route::patch('/{booking}', [BookingController::class, 'update'])->name('update');

        // Acciones del cliente sobre su reserva (JSON plano / PDF, sin documento JSON:API)
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('cancel');
        Route::post('/{booking}/reschedule', [BookingController::class, 'reschedule'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('reschedule');
        Route::post('/{booking}/messages', [BookingController::class, 'sendMessage'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('messages');
        Route::get('/{booking}/receipt', [BookingController::class, 'receipt'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('receipt');
        // Pago asistido: enlace de WhatsApp con el detalle de la reserva ya compuesto.
        // Enlace de pago vivo de la reserva, si lo hay.
        Route::get('/{booking}/payment-link', [PaymentLinkController::class, 'forBooking'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('payment-link');
        Route::post('/{booking}/whatsapp-link', [BookingController::class, 'whatsappLink'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('whatsapp-link');
    });

    // Product reviews (reseñas de usuario con compra verificada)
    Route::prefix('product-reviews')->name('api.v1.product_reviews.')->group(function () {
        Route::get('/eligibility', [ProductReviewController::class, 'eligibility'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])->name('eligibility');
        // Listado de moderación (todas las reseñas) — requiere permiso.
        Route::get('/admin', [ProductReviewController::class, 'adminIndex'])
            ->middleware('permission:product-reviews:moderate')->name('admin-index');
        Route::post('/', [ProductReviewController::class, 'store'])->name('store');
        Route::patch('/{productReview}', [ProductReviewController::class, 'update'])->name('update');
        Route::delete('/{productReview}', [ProductReviewController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('custom-inquiries')->name('api.v1.custom_inquiries.')->group(function () {
        Route::get('/', [CustomInquiryController::class, 'index'])->middleware('permission:custom-inquiries:index')->name('index');
        Route::get('/{customInquiry}', [CustomInquiryController::class, 'show'])->middleware('permission:custom-inquiries:show')->name('show');
    });

    // Suscriptores a ofertas (leads) — gestión admin
    Route::prefix('subscribers')->name('api.v1.subscribers.')->group(function () {
        Route::get('/', [SubscriberController::class, 'index'])->middleware('permission:subscribers:index')->name('index');
        Route::patch('/{subscriber}', [SubscriberController::class, 'update'])->middleware('permission:subscribers:update')->name('update');
        Route::delete('/{subscriber}', [SubscriberController::class, 'destroy'])->middleware('permission:subscribers:delete')->name('destroy');
    });

    Route::prefix('payments')->name('api.v1.payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::post('/checkout', [PaymentController::class, 'checkout'])->name('checkout');
        Route::post('/verify', [PaymentController::class, 'verify'])->name('verify');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
    });

    // ── Enlaces de pago del banco (BAC) ───────────────────────────────────────
    // Sin API del banco, el cobro lo confirma una persona. Cada acción va con su
    // permiso: emitir, confirmar y consultar son decisiones distintas.
    Route::prefix('payment-links')->name('api.v1.payment-links.')->group(function () {
        Route::get('/', [PaymentLinkController::class, 'index'])
            ->middleware('permission:payments:view-all')->name('index');

        // El cliente llega por la referencia desde el correo; el back-office, desde la cola.
        Route::get('/{paymentLink:reference}', [PaymentLinkController::class, 'show'])->name('show');

        Route::post('/{paymentLink:reference}/attach', [PaymentLinkController::class, 'attach'])
            ->middleware('permission:payments:issue-link')->name('attach');
        Route::post('/{paymentLink:reference}/send', [PaymentLinkController::class, 'send'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:payments:issue-link')->name('send');

        // Autorreporte del cliente: multipart (puede traer el comprobante), así
        // que fuera de JSON:API. Limitado para que no se pueda usar como ruido.
        Route::post('/{paymentLink:reference}/report', [PaymentLinkController::class, 'report'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('throttle:10,1')->name('report');
        Route::get('/{paymentLink:reference}/proof', [PaymentLinkController::class, 'proof'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->name('proof');

        Route::post('/{paymentLink:reference}/confirm', [PaymentLinkController::class, 'confirm'])
            ->middleware('permission:payments:confirm-link')->name('confirm');
        Route::post('/{paymentLink:reference}/void', [PaymentLinkController::class, 'void'])
            ->middleware('permission:payments:confirm-link')->name('void');
    });

    // Invoices + DTE, Settings, Reports, Gallery — cada ruta gateada por permiso.
    Route::prefix('invoices')->name('api.v1.invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:invoices:index')->name('index');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices:show')->name('show');
        // Facturación manual: conceptos del catálogo o líneas libres.
        Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:invoices:store')->name('store');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:invoices:delete')->name('destroy');
        Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])
            ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
            ->middleware('permission:invoices:show')->name('pdf');
        Route::patch('/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:invoices:update')->name('update');
        Route::post('/{invoice}/generate-dte', [InvoiceController::class, 'generateDte'])->middleware('permission:invoices:generate-dte')->name('generate-dte');
        Route::get('/{invoice}/preview-dte', [InvoiceController::class, 'previewDte'])->middleware('permission:invoices:generate-dte')->name('preview-dte');
    });
    Route::post('/settings/dte-certificate', [InvoiceController::class, 'uploadCertificate'])
        ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
        ->middleware('permission:invoices:generate-dte')
        ->name('api.v1.settings.dte-certificate');

    // Settings (admin write)
    Route::patch('/settings', [SettingsController::class, 'update'])->middleware('permission:settings:update')->name('api.v1.settings.update');
    Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])
        ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
        ->middleware('permission:settings:update')
        ->name('api.v1.settings.logo');
    Route::post('/settings/about-image', [SettingsController::class, 'uploadAboutImage'])
        ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
        ->middleware('permission:settings:update')
        ->name('api.v1.settings.about-image');

    Route::get('/reports/overview', [ReportController::class, 'overview'])->middleware('permission:reports:view')->name('api.v1.reports.overview');

    // Gallery (escritura admin; lectura pública más abajo)
    Route::post('/gallery', [GalleryController::class, 'store'])
        ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class])
        ->middleware('permission:gallery:store')
        ->name('api.v1.gallery.store');
    Route::delete('/gallery/{galleryItem}', [GalleryController::class, 'destroy'])->middleware('permission:gallery:delete')->name('api.v1.gallery.destroy');
    Route::patch('/gallery/reorder', [GalleryController::class, 'reorder'])->middleware('permission:gallery:reorder')->name('api.v1.gallery.reorder');
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
    // Tipos de vehículo disponibles (para poblar filtros en el frontend).
    Route::get('/types', [TransportVehicleController::class, 'types'])->name('types');
    Route::get('/{transportVehicle}', [TransportVehicleController::class, 'show'])->name('show');
    Route::get('/{transportVehicle}/availability', [TransportVehicleController::class, 'checkAvailability'])->name('availability');
});

Route::get('/currencies', [CurrencyController::class, 'index'])->name('api.v1.currencies.index');
Route::get('/currencies/{currency}', [CurrencyController::class, 'show'])->name('api.v1.currencies.show');

// Tour Categories (public read)
Route::get('/tour-categories', [TourCategoryController::class, 'index'])->name('api.v1.tour_categories.index');

// Reviews (public read)
Route::get('/reviews', [ReviewController::class, 'index'])->name('api.v1.reviews.index');

// Product reviews (public read — reseñas aprobadas de un producto)
Route::get('/product-reviews', [ProductReviewController::class, 'index'])->name('api.v1.product_reviews.index');

Route::post('/custom-inquiries', [CustomInquiryController::class, 'store'])->name('api.v1.custom_inquiries.store')->middleware('throttle:forms');

// Suscripción pública a ofertas (leads)
Route::post('/subscribers', [SubscriberController::class, 'store'])->name('api.v1.subscribers.store')->middleware('throttle:forms');
Route::post('/subscribers/unsubscribe', [SubscriberController::class, 'unsubscribe'])
    ->name('api.v1.subscribers.unsubscribe')
    ->middleware('throttle:forms')
    ->withoutMiddleware([ValidateJsonApiHeaders::class, ValidateJsonApiDocument::class]);

// Gallery (public read)
Route::get('/gallery', [GalleryController::class, 'index'])->name('api.v1.gallery.index');

// Settings (public read — returns safe subset without credentials)
Route::get('/settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
