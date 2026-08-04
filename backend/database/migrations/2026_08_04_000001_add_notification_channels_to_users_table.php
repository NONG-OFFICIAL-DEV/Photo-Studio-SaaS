<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user notification channel preferences (mail/system/telegram) plus the
 * fields needed to link a user's own Telegram account to the PLATFORM bot
 * (see config('services.platform_telegram')) — a completely separate bot
 * from the one each tenant connects for their own customers. Mirrors the
 * exact telegram_chat_id/telegram_link_token/telegram_linked_at shape
 * already used on customers (see add_telegram_fields_to_customers_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('notification_channels')->nullable()->after('avatar_path');
            $table->string('telegram_chat_id')->nullable()->after('notification_channels');
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
            $table->dateTime('telegram_linked_at')->nullable()->after('telegram_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notification_channels', 'telegram_chat_id', 'telegram_link_token', 'telegram_linked_at']);
        });
    }
};
