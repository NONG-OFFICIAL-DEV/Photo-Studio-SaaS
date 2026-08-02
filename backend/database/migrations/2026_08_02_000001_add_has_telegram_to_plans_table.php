<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same shape as has_reports/has_api_access — a premium add-on, off by
     * default, so existing plans don't silently gain Telegram delivery
     * until a super admin explicitly enables it per plan.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_telegram')->default(false)->after('has_api_access');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_telegram');
        });
    }
};
