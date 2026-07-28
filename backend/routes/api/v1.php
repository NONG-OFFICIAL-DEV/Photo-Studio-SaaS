<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Customer\CustomerNoteController;
use App\Http\Controllers\Api\V1\Customer\CustomerTagController;
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
});
