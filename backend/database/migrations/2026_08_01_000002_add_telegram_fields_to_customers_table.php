<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * telegram_link_token is the one-time code embedded in the deep link
     * (t.me/<bot>?start=<token>) staff share with a customer; the webhook
     * exchanges it for the customer's real telegram_chat_id once they tap
     * "start" in their own Telegram app (bots can't message a user first,
     * so this handshake is unavoidable). Unique across all tenants since a
     * customer's token isn't scoped by anything else at the DB level.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('avatar_path');
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
            $table->dateTime('telegram_linked_at')->nullable()->after('telegram_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token', 'telegram_linked_at']);
        });
    }
};
