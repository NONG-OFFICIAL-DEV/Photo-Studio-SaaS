<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform root table. A tenant is one photography studio account.
     * It intentionally has no tenant_id of its own — it IS the tenant.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('timezone')->default('Asia/Phnom_Penh');
            $table->string('currency', 3)->default('USD');
            $table->string('locale', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
