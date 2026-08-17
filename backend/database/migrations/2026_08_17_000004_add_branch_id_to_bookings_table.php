<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable — soft tagging only. Deleting a branch untags its
            // bookings rather than destroying booking history.
            $table->uuid('branch_id')->nullable()->after('tenant_id');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
