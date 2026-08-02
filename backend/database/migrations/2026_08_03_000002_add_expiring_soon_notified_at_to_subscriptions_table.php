<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether the "expiring soon" reminder has already fired for the
 * subscription's *current* period, so the daily sweep doesn't re-notify
 * every day between the threshold and the actual expiry. Reset to null
 * whenever the period is extended (see SubscriptionService::renew()) so a
 * later renewal becomes eligible for its own fresh warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('expiring_soon_notified_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('expiring_soon_notified_at');
        });
    }
};
