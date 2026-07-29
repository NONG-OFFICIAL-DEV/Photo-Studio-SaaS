<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pay_type')->default('salary')->after('status'); // App\Enums\PayType
            $table->decimal('base_pay', 12, 2)->nullable()->after('pay_type');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('base_pay');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pay_type', 'base_pay', 'commission_rate']);
        });
    }
};
