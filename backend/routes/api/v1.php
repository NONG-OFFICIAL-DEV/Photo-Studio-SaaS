<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Booking\BookingController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Customer\CustomerNoteController;
use App\Http\Controllers\Api\V1\Customer\CustomerTagController;
use App\Http\Controllers\Api\V1\Order\EditingTaskController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Service\ServiceAddOnController;
use App\Http\Controllers\Api\V1\Service\ServiceCategoryController;
use App\Http\Controllers\Api\V1\Service\ServiceController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'pong',
    'data' => ['version' => 'v1'],
    'meta' => [],
]));

Route::prefix('auth')->name('auth.')->group(function () {
    // Guest
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink']);
        Route::post('/password/reset', [PasswordResetController::class, 'reset']);
    });

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('email.verify');

    // Authenticated
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/resend', [EmailVerificationController::class, 'resend']);
    });
});

/*
 * Tenant-scoped application routes. Every route here runs behind the full
 * chain: JWT auth -> tenant resolution -> subscription gate. Modules
 * (customers, bookings, invoices, ...) register their routes inside this
 * group in later phases.
 */
Route::middleware(['auth:api', 'tenant', 'subscription.active'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');

    Route::prefix('customers')->name('customers.')->group(function () {
        // Static segments must be registered before {customer} so they
        // aren't swallowed by the wildcard.
        Route::get('/export', [CustomerController::class, 'export']);
        Route::post('/import', [CustomerController::class, 'import']);

        Route::apiResource('tags', CustomerTagController::class)->except(['show']);

        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::put('/{customer}', [CustomerController::class, 'update']);
        Route::delete('/{customer}', [CustomerController::class, 'destroy']);

        Route::post('/{customer}/favorite', [CustomerController::class, 'toggleFavorite']);
        Route::post('/{customer}/blacklist', [CustomerController::class, 'blacklist']);
        Route::post('/{customer}/unblacklist', [CustomerController::class, 'unblacklist']);

        Route::post('/{customer}/notes', [CustomerNoteController::class, 'store']);
        Route::delete('/{customer}/notes/{note}', [CustomerNoteController::class, 'destroy']);
    });

    Route::prefix('bookings')->name('bookings.')->group(function () {
        // Static segment before {booking} so it isn't swallowed by the wildcard.
        Route::get('/calendar', [BookingController::class, 'calendar']);

        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        Route::get('/{booking}', [BookingController::class, 'show']);
        Route::put('/{booking}', [BookingController::class, 'update']);
        Route::delete('/{booking}', [BookingController::class, 'destroy']);

        Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::post('/{booking}/start', [BookingController::class, 'start']);
        Route::post('/{booking}/complete', [BookingController::class, 'complete']);
        Route::post('/{booking}/no-show', [BookingController::class, 'noShow']);
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel']);
    });

    Route::prefix('services')->name('services.')->group(function () {
        // Static segments before {service} so they aren't swallowed by the wildcard.
        Route::apiResource('categories', ServiceCategoryController::class)->except(['show']);
        Route::apiResource('addons', ServiceAddOnController::class)->except(['show']);

        Route::get('/', [ServiceController::class, 'index']);
        Route::post('/', [ServiceController::class, 'store']);
        Route::get('/{service}', [ServiceController::class, 'show']);
        Route::put('/{service}', [ServiceController::class, 'update']);
        Route::delete('/{service}', [ServiceController::class, 'destroy']);
    });

    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::put('/{order}', [OrderController::class, 'update']);
        Route::delete('/{order}', [OrderController::class, 'destroy']);

        Route::post('/{order}/confirm', [OrderController::class, 'confirm']);
        Route::post('/{order}/start-production', [OrderController::class, 'startProduction']);
        Route::post('/{order}/ready-for-delivery', [OrderController::class, 'readyForDelivery']);
        Route::post('/{order}/deliver', [OrderController::class, 'deliver']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
    });

    Route::prefix('editing-tasks')->name('editing-tasks.')->group(function () {
        Route::get('/', [EditingTaskController::class, 'index']);

        Route::post('/{editingTask}/assign', [EditingTaskController::class, 'assign']);
        Route::post('/{editingTask}/start', [EditingTaskController::class, 'start']);
        Route::post('/{editingTask}/in-review', [EditingTaskController::class, 'markInReview']);
        Route::post('/{editingTask}/request-revision', [EditingTaskController::class, 'requestRevision']);
        Route::post('/{editingTask}/complete', [EditingTaskController::class, 'complete']);
    });
});
