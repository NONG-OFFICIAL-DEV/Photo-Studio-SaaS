<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scan-to-pay QR image (e.g. KHQR) shown on invoice PDFs — a plain
     * nullable path column mirroring tenants.logo_path, not the JSON
     * `settings` blob, since it's an uploaded file rather than a scalar
     * preference.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('qr_payment_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('qr_payment_path');
        });
    }
};
