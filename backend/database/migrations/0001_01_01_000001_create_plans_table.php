<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide subscription plan catalog, managed by the Super Admin.
     * Not tenant-scoped — every tenant reads the same plan list.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_quarterly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();

            // Feature limits. Null = unlimited.
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('storage_limit_gb')->nullable();
            $table->unsignedInteger('monthly_order_limit')->nullable();

            $table->boolean('has_watermark_gallery')->default(true);
            $table->boolean('has_online_gallery')->default(true);
            $table->boolean('has_reports')->default(false);
            $table->boolean('has_api_access')->default(false);

            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
