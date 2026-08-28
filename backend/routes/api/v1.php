<?php

use App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditController;
use App\Http\Controllers\Api\V1\Admin\AdminPaymentConfirmationController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanFeatureListingController;
use App\Http\Controllers\Api\V1\Admin\AdminPlatformSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminRolePermissionController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantController;
use App\Http\Controllers\Api\V1\Admin\AdminTenantRolePermissionController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Album\AlbumController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceController;
use App\Http\Controllers\Api\V1\Audit\AuditController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorAuthController;
use App\Http\Controllers\Api\V1\Billing\BillingController;
use App\Http\Controllers\Api\V1\Booking\BookingController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Commission\CommissionEntryController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Customer\CustomerNoteController;
use App\Http\Controllers\Api\V1\Customer\CustomerTagController;
use App\Http\Controllers\Api\V1\Customer\CustomerTelegramController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Expense\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\Expense\ExpenseController;
use App\Http\Controllers\Api\V1\Inventory\InventoryItemController;
use App\Http\Controllers\Api\V1\Inventory\InventoryMovementController;
use App\Http\Controllers\Api\V1\Invoice\InvoiceController;
use App\Http\Controllers\Api\V1\Invoice\PaymentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferencesController;
use App\Http\Controllers\Api\V1\Order\EditingTaskController;
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Package\PackageController;
use App\Http\Controllers\Api\V1\Payroll\PayrollEntryController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PlanLimitController;
use App\Http\Controllers\Api\V1\PlatformTelegramWebhookController;
use App\Http\Controllers\Api\V1\Report\ReportController;
use App\Http\Controllers\Api\V1\Service\ServiceAddOnController;
use App\Http\Controllers\Api\V1\Service\ServiceCategoryController;
use App\Http\Controllers\Api\V1\Service\ServiceController;
use App\Http\Controllers\Api\V1\Settings\TelegramSettingsController;
use App\Http\Controllers\Api\V1\Settings\TenantSettingsController;
use App\Http\Controllers\Api\V1\Telegram\TelegramActivityController;
use App\Http\Controllers\Api\V1\Telegram\TelegramWebhookController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'pong',
    'data' => ['version' => 'v1'],
    'meta' => [],
]));

/*
 * Public, unauthenticated — for a pricing/marketing page (this app's own
 * or a separate website) to display active plans. No API key needed:
 * this is the same data a visitor would see on a pricing page anyway.
 */
Route::get('/plans', [PlanController::class, 'index'])->name('plans.public');

Route::prefix('auth')->name('auth.')->group(function () {
    // Guest
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'googleAuth']);
        Route::post('/google/register', [AuthController::class, 'googleRegister']);
        Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
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
        Route::put('/email', [AuthController::class, 'updateEmail']);
        Route::put('/password', [AuthController::class, 'updatePassword']);

        Route::prefix('two-factor')->name('two-factor.')->group(function () {
            Route::post('/setup', [TwoFactorAuthController::class, 'setup']);
            Route::post('/confirm', [TwoFactorAuthController::class, 'confirm']);
            Route::post('/disable', [TwoFactorAuthController::class, 'disable']);
        });
    });
});

/*
 * Purely user-scoped, no tenant resolution needed — reachable by both a
 * tenant user (even one whose subscription just lapsed) and a super admin
 * (no tenant at all), same as /auth/me above.
 */
Route::middleware('auth:api')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/preferences', [NotificationPreferencesController::class, 'show']);
    Route::put('/preferences', [NotificationPreferencesController::class, 'update']);
    Route::post('/telegram/link', [NotificationPreferencesController::class, 'linkTelegram']);
    Route::post('/telegram/unlink', [NotificationPreferencesController::class, 'unlinkTelegram']);
});

/*
 * Tenant-scoped application routes. Every route here runs behind the full
 * chain: JWT auth -> tenant resolution -> subscription gate. Modules
 * (customers, bookings, invoices, ...) register their routes inside this
 * group in later phases.
 */
Route::middleware(['auth:api', 'tenant', 'subscription.active'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('email.verified')->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('email.verified')->name('users.update');
    Route::put('/users/{user}/profile', [UserController::class, 'updateProfile'])->middleware('email.verified')->name('users.update-profile');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('email.verified')->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->middleware('email.verified')->name('users.reactivate');

    Route::get('/plan-limits', [PlanLimitController::class, 'show'])->name('plan-limits.show');

    Route::apiResource('branches', BranchController::class)->except(['show']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

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

        Route::post('/{customer}/telegram/link', [CustomerTelegramController::class, 'link'])->middleware('plan.feature:has_telegram');
        Route::post('/{customer}/telegram/unlink', [CustomerTelegramController::class, 'unlink'])->middleware('plan.feature:has_telegram');
        Route::post('/{customer}/telegram/send', [CustomerTelegramController::class, 'sendFiles'])->middleware('plan.feature:has_telegram');
        Route::get('/{customer}/telegram/activity', [CustomerTelegramController::class, 'activity']);
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

    Route::prefix('packages')->name('packages.')->group(function () {
        Route::get('/', [PackageController::class, 'index']);
        Route::post('/', [PackageController::class, 'store']);
        Route::get('/{package}', [PackageController::class, 'show']);
        Route::put('/{package}', [PackageController::class, 'update']);
        Route::delete('/{package}', [PackageController::class, 'destroy']);

        Route::post('/{package}/telegram/send', [PackageController::class, 'sendTelegram'])->middleware('plan.feature:has_telegram');
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

    Route::prefix('albums')->name('albums.')->group(function () {
        Route::get('/', [AlbumController::class, 'index']);
        Route::post('/', [AlbumController::class, 'store']);
        Route::get('/{album}', [AlbumController::class, 'show']);
        Route::put('/{album}', [AlbumController::class, 'update']);
        Route::delete('/{album}', [AlbumController::class, 'destroy']);

        Route::post('/{album}/start', [AlbumController::class, 'start']);
        Route::post('/{album}/ready', [AlbumController::class, 'markReady']);
        Route::post('/{album}/deliver', [AlbumController::class, 'deliver']);
        Route::post('/{album}/archive', [AlbumController::class, 'archive']);
    });

    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/{invoice}', [InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);

        Route::post('/{invoice}/send', [InvoiceController::class, 'send']);
        Route::post('/{invoice}/void', [InvoiceController::class, 'void']);

        Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
        Route::get('/{invoice}/share-link', [InvoiceController::class, 'shareLink']);
        Route::post('/{invoice}/telegram/send', [InvoiceController::class, 'sendTelegram'])->middleware('plan.feature:has_telegram');

        Route::post('/{invoice}/payments', [PaymentController::class, 'store']);
        Route::delete('/{invoice}/payments/{payment}', [PaymentController::class, 'destroy']);
    });

    Route::prefix('expenses')->name('expenses.')->group(function () {
        // Static segment before {expense} so it isn't swallowed by the wildcard.
        Route::apiResource('categories', ExpenseCategoryController::class)->except(['show']);

        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/{expense}', [ExpenseController::class, 'show']);
        Route::put('/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
    });

    Route::prefix('inventory-items')->name('inventory-items.')->group(function () {
        Route::get('/', [InventoryItemController::class, 'index']);
        Route::post('/', [InventoryItemController::class, 'store']);
        Route::get('/{inventoryItem}', [InventoryItemController::class, 'show']);
        Route::put('/{inventoryItem}', [InventoryItemController::class, 'update']);
        Route::delete('/{inventoryItem}', [InventoryItemController::class, 'destroy']);

        Route::post('/{inventoryItem}/movements', [InventoryMovementController::class, 'store']);
        Route::delete('/{inventoryItem}/movements/{movement}', [InventoryMovementController::class, 'destroy']);
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        // Static segments before {attendanceRecord} so they aren't swallowed by the wildcard.
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::post('/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/clock-out', [AttendanceController::class, 'clockOut']);

        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::put('/{attendanceRecord}', [AttendanceController::class, 'update']);
        Route::delete('/{attendanceRecord}', [AttendanceController::class, 'destroy']);
    });

    Route::prefix('commission-entries')->name('commission-entries.')->group(function () {
        Route::get('/', [CommissionEntryController::class, 'index']);
        Route::post('/', [CommissionEntryController::class, 'store']);
        Route::put('/{commissionEntry}', [CommissionEntryController::class, 'update']);
        Route::delete('/{commissionEntry}', [CommissionEntryController::class, 'destroy']);
    });

    Route::prefix('payroll-entries')->name('payroll-entries.')->group(function () {
        Route::get('/', [PayrollEntryController::class, 'index']);
        Route::post('/', [PayrollEntryController::class, 'store']);
        Route::get('/{payrollEntry}', [PayrollEntryController::class, 'show']);
        Route::put('/{payrollEntry}', [PayrollEntryController::class, 'update']);
        Route::delete('/{payrollEntry}', [PayrollEntryController::class, 'destroy']);

        Route::post('/{payrollEntry}/pay', [PayrollEntryController::class, 'pay']);
    });

    Route::prefix('reports')->name('reports.')->middleware('plan.feature:has_reports')->group(function () {
        Route::get('/revenue', [ReportController::class, 'revenue']);
        Route::get('/revenue/export', [ReportController::class, 'exportRevenue']);
        Route::get('/bookings', [ReportController::class, 'bookings']);
        Route::get('/bookings/export', [ReportController::class, 'exportBookings']);
        Route::get('/orders', [ReportController::class, 'orders']);
        Route::get('/orders/export', [ReportController::class, 'exportOrders']);
        Route::get('/expenses', [ReportController::class, 'expenses']);
        Route::get('/expenses/export', [ReportController::class, 'exportExpenses']);
    });

    Route::prefix('telegram')->name('telegram.')->group(function () {
        Route::get('/activity', [TelegramActivityController::class, 'index']);
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        // Static segments before '/' so they aren't swallowed unexpectedly.
        Route::get('/export', [TenantSettingsController::class, 'export']);
        Route::post('/logo', [TenantSettingsController::class, 'uploadLogo']);
        Route::post('/qr-payment', [TenantSettingsController::class, 'uploadQrPayment']);
        Route::post('/telegram/connect', [TelegramSettingsController::class, 'connect'])->middleware('plan.feature:has_telegram');
        Route::post('/telegram/disconnect', [TelegramSettingsController::class, 'disconnect'])->middleware('plan.feature:has_telegram');

        Route::get('/', [TenantSettingsController::class, 'show']);
        Route::put('/', [TenantSettingsController::class, 'update'])->middleware('email.verified');
    });

    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/activity', [AuditController::class, 'activityLog']);
        Route::get('/log', [AuditController::class, 'auditLog']);
        Route::get('/login-history', [AuditController::class, 'loginHistory']);
        Route::get('/security-events', [AuditController::class, 'securityEvents']);
        Route::get('/api-logs', [AuditController::class, 'apiLogs']);
    });
});

/*
 * Signed, unauthenticated invoice PDF link — shared with customers over
 * Telegram/WhatsApp/SMS, who have no account, so this can't sit behind
 * auth:api/tenant like every other invoice route. Same 'signed' pattern as
 * the email-verification link above; expires per the signature baked in
 * by InvoiceController::shareLink() (see routes/api/v1.php's auth group).
 */
Route::get('/invoices/{invoice}/public-pdf', [InvoiceController::class, 'publicPdf'])
    ->middleware('signed')
    ->name('invoices.public-pdf');

/*
 * Telegram calls this directly — no account, so it can't sit behind
 * auth:api/tenant either, and Telegram can't produce Laravel's 'signed'
 * middleware's signature. Authenticity is instead verified inside
 * TelegramWebhookController via the secret_token this tenant's bot
 * registered with setWebhook (see TelegramSettingsController::connect()).
 */
Route::post('/webhooks/telegram/{tenant}', [TelegramWebhookController::class, 'handle'])
    ->name('webhooks.telegram');

/*
 * Same reasoning as the tenant webhook above, but for the single
 * platform-wide admin bot (see config('services.platform_telegram')) —
 * authenticity verified inside PlatformTelegramWebhookController.
 */
Route::post('/webhooks/telegram-platform', [PlatformTelegramWebhookController::class, 'handle'])
    ->name('webhooks.telegram-platform');

/*
 * Tenant self-service billing. Deliberately OUTSIDE `subscription.active` —
 * a tenant whose subscription has lapsed must still be able to reach this
 * to renew, or the middleware that blocks them would also block the only
 * way to fix it. `tenant` middleware still applies (a tenant deactivated
 * outright by the platform, is_active=false, is a separate, more severe
 * gate that correctly still blocks billing too).
 */
Route::middleware(['auth:api', 'tenant'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'show']);
    Route::get('/plans', [BillingController::class, 'plans']);
    Route::get('/payments', [BillingController::class, 'payments']);
    Route::post('/renew', [BillingController::class, 'renew']);
    Route::put('/plan', [BillingController::class, 'changePlan']);
    Route::post('/cancel', [BillingController::class, 'cancel']);
    Route::post('/resume', [BillingController::class, 'resume']);
    Route::post('/payment-claims', [BillingController::class, 'submitPaymentClaim']);
});

/*
 * Platform admin panel. Deliberately OUTSIDE the tenant-scoped group above —
 * super admins have no tenant_id and operate across every tenant, so this
 * group runs behind only auth:api + super-admin (see EnsureSuperAdmin and
 * IdentifyTenant's super-admin exemption).
 */
Route::prefix('admin')->name('admin.')->middleware(['auth:api', 'super-admin'])->group(function () {
    Route::get('/analytics', [AdminAnalyticsController::class, 'stats']);

    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [AdminTenantController::class, 'index']);
        Route::get('/{tenant}', [AdminTenantController::class, 'show']);
        Route::post('/{tenant}/suspend', [AdminTenantController::class, 'suspend']);
        Route::post('/{tenant}/activate', [AdminTenantController::class, 'activate']);
        Route::post('/{tenant}/delete', [AdminTenantController::class, 'destroy']);

        Route::prefix('{tenant}/subscription')->name('subscription.')->group(function () {
            Route::put('/plan', [AdminTenantController::class, 'changePlan']);
            Route::post('/renew', [AdminTenantController::class, 'renewSubscription']);
            Route::post('/cancel', [AdminTenantController::class, 'cancelSubscription']);
            Route::post('/resume', [AdminTenantController::class, 'resumeSubscription']);
            Route::post('/suspend', [AdminTenantController::class, 'suspendSubscription']);
            Route::post('/reactivate', [AdminTenantController::class, 'reactivateSubscription']);
            Route::get('/payments', [AdminTenantController::class, 'subscriptionPayments']);
        });

        Route::prefix('{tenant}/role-permissions')->name('role-permissions.')->group(function () {
            Route::get('/', [AdminTenantRolePermissionController::class, 'index']);
            Route::put('/{role}', [AdminTenantRolePermissionController::class, 'update']);
        });

        Route::prefix('{tenant}/users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/{user}/deactivate', [AdminUserController::class, 'deactivate']);
            Route::post('/{user}/reactivate', [AdminUserController::class, 'reactivate']);
            Route::post('/{user}/reset-password', [AdminUserController::class, 'sendPasswordReset']);
        });
    });

    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/', [AdminPlanController::class, 'index']);
        Route::post('/', [AdminPlanController::class, 'store']);
        Route::put('/{plan}', [AdminPlanController::class, 'update']);
        Route::delete('/{plan}', [AdminPlanController::class, 'destroy']);
    });

    Route::prefix('plan-feature-listings')->name('plan-feature-listings.')->group(function () {
        Route::get('/', [AdminPlanFeatureListingController::class, 'index']);
        Route::post('/', [AdminPlanFeatureListingController::class, 'store']);
        Route::put('/{planFeatureListing}', [AdminPlanFeatureListingController::class, 'update']);
        Route::delete('/{planFeatureListing}', [AdminPlanFeatureListingController::class, 'destroy']);
    });

    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/activity', [AdminAuditController::class, 'activityLog']);
        Route::get('/log', [AdminAuditController::class, 'auditLog']);
        Route::get('/login-history', [AdminAuditController::class, 'loginHistory']);
        Route::get('/security-events', [AdminAuditController::class, 'securityEvents']);
        Route::get('/api-logs', [AdminAuditController::class, 'apiLogs']);
    });

    Route::prefix('role-permissions')->name('role-permissions.')->group(function () {
        Route::get('/', [AdminRolePermissionController::class, 'index']);
        Route::put('/{role}', [AdminRolePermissionController::class, 'update']);
    });

    Route::prefix('platform-settings')->name('platform-settings.')->group(function () {
        Route::get('/', [AdminPlatformSettingController::class, 'show']);
        Route::put('/', [AdminPlatformSettingController::class, 'update']);
        Route::post('/khqr', [AdminPlatformSettingController::class, 'uploadKhqr']);
    });

    Route::prefix('payment-claims')->name('payment-claims.')->group(function () {
        Route::get('/', [AdminPaymentConfirmationController::class, 'index']);
        Route::post('/{claim}/confirm', [AdminPaymentConfirmationController::class, 'confirm']);
        Route::post('/{claim}/reject', [AdminPaymentConfirmationController::class, 'reject']);
    });
});
