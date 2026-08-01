<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedicated columns rather than the JSON `settings` blob — these are a
     * credential (bot token) and a per-tenant webhook secret, not a
     * user-facing scalar preference, and TenantResource must be able to
     * withhold them from the generic settings payload it returns (unlike
     * every key in Tenant::SETTINGS_DEFAULTS, which is always echoed back
     * as-is). Same reasoning as qr_payment_path being a dedicated column.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('qr_payment_path');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->string('telegram_webhook_secret')->nullable()->after('telegram_bot_username');
            $table->dateTime('telegram_connected_at')->nullable()->after('telegram_webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['telegram_bot_token', 'telegram_bot_username', 'telegram_webhook_secret', 'telegram_connected_at']);
        });
    }
};
